<?php

namespace App\Services;

use App\Enums\FeeBasis;
use App\Enums\ReportStatus;
use App\Models\Booking;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Services\Finance\FinanceCalculator;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Projects confirmed booking revenue; this does not verify a payout or bank transfer. */
class HostexIncomeService
{
    public static function pendingQuery(?array $villaIds, string $from, string $to): Builder
    {
        $basis = config('services.hostex.income_date_basis') === 'check_out' ? 'check_out' : 'check_in';

        return Booking::query()->where('source', 'hostex')
            ->when($villaIds !== null, fn ($q) => $q->whereIn('villa_id', $villaIds ?: [0]))
            ->where(fn ($q) => $q->whereNotNull('hostex_income_error')
                ->orWhere(fn ($new) => $new->where('hostex_status', 'accepted')->whereNull('hostex_income_synced_at')))
            ->where(fn ($q) => $q->whereBetween($basis, [$from, $to])->orWhereHas('incomes', fn ($income) => $income
                ->whereNotNull('hostex_stay_code')->whereBetween('payment_date', [$from, $to])));
    }

    /** Called within the villa's Hostex transaction and lock. */
    public function sync(Booking $booking): array
    {
        $counts = ['income_created' => 0, 'income_updated' => 0, 'income_voided' => 0, 'income_pending' => 0];
        if (! config('services.hostex.auto_income_enabled') || config('propertyme.channel_manager_provider') !== 'hostex'
            || $booking->source !== 'hostex' || ! filled($booking->hostex_stay_code) || ! $booking->exists || $booking->trashed()) {
            return $counts;
        }
        $income = Income::withTrashed()->where('hostex_stay_code', $booking->hostex_stay_code)->lockForUpdate()->first();
        if ($income && ($income->booking_id !== $booking->id || $income->villa_id !== $booking->villa_id || $income->platform_id !== $booking->platform_id)) {
            return $this->finish($booking, $counts, 'allocation');
        }
        $other = Income::withTrashed()->where(function ($q) use ($booking) {
            $q->where('booking_id', $booking->id);
            if ($booking->booking_ref) {
                $q->orWhere(fn ($refs) => $refs->where('villa_id', $booking->villa_id)->where('platform_id', $booking->platform_id)
                    ->where('booking_ref', $booking->booking_ref));
            }
        })->when($income, fn ($q) => $q->whereKeyNot($income->id))->lockForUpdate()->get();
        $manual = $other->filter(fn ($record) => ! $record->isFromHostex());
        if ($manual->isNotEmpty()) {
            if (! $this->void($income, 'existing_income', $counts)) {
                return $this->finish($booking, $counts, 'locked');
            }

            $active = $manual->filter(fn ($record) => ! $record->trashed());

            return $this->finish($booking, $counts, $active->isEmpty() ? 'deleted_income'
                : ($active->contains(fn ($record) => $record->booking_id === $booking->id) ? null : 'unlinked_income'));
        }
        if ($booking->hostex_status !== 'accepted') {
            $ok = $this->void($income, 'inactive_booking', $counts);

            return $this->finish($booking, $counts, $ok ? null : 'locked');
        }
        $problem = $this->moneyProblem($booking);
        if ($problem) {
            $ok = $this->void($income, 'incomplete', $counts);

            return $this->finish($booking, $counts, $ok ? $problem : 'locked');
        }
        $basis = config('services.hostex.income_date_basis');
        if (! in_array($basis, ['check_in', 'check_out'], true)) {
            return $this->finish($booking, $counts, 'date_basis');
        }
        $date = $booking->{$basis}->toDateString();
        $villa = $booking->villa;
        $history = $villa->feeOn($date);
        $feeBasis = $income?->fee_basis ?? $history?->fee_basis ?? $villa->fee_basis;
        $percentage = $income?->fee_percentage ?? $history?->fee_percentage ?? $villa->fee_percentage;
        if ($feeBasis === FeeBasis::Custom) {
            $ok = $this->void($income, 'incomplete', $counts);

            return $this->finish($booking, $counts, $ok ? 'custom_fee' : 'locked');
        }
        $line = FinanceCalculator::incomeLine($booking->hostex_rate_amount, $booking->hostex_commission_amount, 0, false, $percentage, $feeBasis);
        // Separate stays in a multi-stay order can share a reference. The stay
        // code remains the unique income identity; never reuse another stay's row.
        $reference = $other->isEmpty() ? $booking->booking_ref : null;
        $attributes = [
            'booking_id' => $booking->id, 'villa_id' => $booking->villa_id, 'platform_id' => $booking->platform_id,
            'booking_ref' => $reference, 'payment_date' => $date,
            'gross_amount' => $booking->hostex_rate_amount, 'platform_fee' => $booking->hostex_commission_amount,
            'other_deduction' => '0.00', 'is_net_of_platform' => false,
            'net_revenue' => $line['net_revenue'], 'fee_percentage' => $percentage, 'fee_basis' => $feeBasis,
            'fee_base_amount' => $line['fee_base_amount'], 'fee_amount' => $line['fee_amount'],
            'hostex_stay_code' => $booking->hostex_stay_code, 'hostex_date_basis' => $basis, 'hostex_void_reason' => null,
        ];
        $new = ! $income;
        $oldDate = $income?->payment_date?->toDateString();
        $restore = $income?->trashed() ?? false;
        if ($restore && ! in_array($income->hostex_void_reason, ['inactive_booking', 'incomplete', 'existing_income', 'reviewed_restore'], true)) {
            return $this->finish($booking, $counts, 'deleted_income');
        }
        $income ??= new Income;
        $income->forceFill($attributes);
        if ($new || $restore || $income->isDirty()) {
            if ($this->locked($booking->villa_id, array_filter([$oldDate, $date]))) {
                return $this->finish($booking, $counts, 'locked');
            }
            if ($restore) {
                $income->restore();
            } else {
                $income->save();
            }
            $counts[$new ? 'income_created' : 'income_updated']++;
        }

        return $this->finish($booking, $counts);
    }

    private function void(?Income $income, string $reason, array &$counts): bool
    {
        if (! $income || $income->trashed()) {
            return true;
        }
        if ($this->locked($income->villa_id, [$income->payment_date->toDateString()])) {
            return false;
        }
        // Free the legacy reference as IncomeService::delete does, retaining the
        // stay identity and amounts on the archived automatic record.
        $income->forceFill(['hostex_void_reason' => $reason, 'booking_ref' => null])->save();
        $income->delete();
        $counts['income_voided']++;

        return true;
    }

    private function moneyProblem(Booking $booking): ?string
    {
        if ($booking->hostex_rate_amount === null || $booking->hostex_commission_amount === null) {
            return 'missing_amounts';
        }
        if ($booking->hostex_rate_currency !== 'IDR' || $booking->hostex_commission_currency !== 'IDR') {
            return 'currency';
        }
        if (BigDecimal::of($booking->hostex_commission_amount)->isGreaterThan($booking->hostex_rate_amount)) {
            return 'invalid_amounts';
        }

        return null;
    }

    private function locked(int $villaId, array $dates): bool
    {
        foreach (array_unique(array_map(fn ($date) => Carbon::parse($date)->format('Y-m'), $dates)) as $month) {
            $date = Carbon::parse($month.'-01');
            $report = MonthlyReport::where('villa_id', $villaId)->where('year', $date->year)->where('month', $date->month)->lockForUpdate()->first();
            if ($report?->status === ReportStatus::Final) {
                return true;
            }
        }

        return false;
    }

    private function finish(Booking $booking, array $counts, ?string $error = null): array
    {
        $booking->forceFill(['hostex_income_error' => $error, 'hostex_income_synced_at' => now()])->save();
        $counts['income_pending'] = (int) ($error !== null);

        return $counts;
    }
}
