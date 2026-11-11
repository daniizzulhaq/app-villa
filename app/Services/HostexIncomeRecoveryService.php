<?php

namespace App\Services;

use App\Enums\FeeBasis;
use App\Models\Booking;
use App\Models\Income;
use App\Models\User;
use App\Services\Finance\FinanceCalculator;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicitly reviewed recovery; ordinary synchronization still respects deleted entries. */
class HostexIncomeRecoveryService
{
    public function __construct(private HostexIncomeService $incomes, private ReportPeriodGuard $periods) {}

    public function review(Booking $booking): array
    {
        $related = $this->related($booking)->orderBy('id')->get();
        $candidates = $related->filter(fn (Income $income) => $this->matches($booking, $income));
        $projections = [];
        $issues = [];
        $tokens = [];
        foreach ($candidates as $income) {
            try {
                $this->assertSingleRecord($related, $income);
                $projections[$income->id] = $this->project($booking, $income);
                $tokens[$income->id] = $this->fingerprint($booking, $income);
            } catch (ValidationException $exception) {
                $issues[$income->id] = collect($exception->errors())->flatten()->first();
            }
        }

        return compact('related', 'candidates', 'projections', 'issues', 'tokens');
    }

    public function recover(Booking $booking, int $incomeId, string $reviewToken, User $actor): Income
    {
        // The same lock serializes this action with the Hostex importer.
        return Cache::store('file')->lock('hostex-sync', 300)->block(5, function () use ($booking, $incomeId, $reviewToken, $actor) {
            return DB::transaction(function () use ($booking, $incomeId, $reviewToken, $actor) {
                $locked = $this->periods->lockVillas([$booking->villa_id]);
                $booking = Booking::lockForUpdate()->findOrFail($booking->id);
                abort_unless($actor->can('update', $booking), 403);
                $this->periods->assertScope($booking->villa_id, $locked);
                $related = $this->related($booking)->orderBy('id')->lockForUpdate()->get();
                $income = $related->firstWhere('id', $incomeId);
                if (! $income || ! $this->matches($booking, $income)) {
                    $this->fail('The deleted income no longer matches this booking. Reload the review.');
                }
                $this->assertSingleRecord($related, $income);
                $projection = $this->project($booking, $income);
                if (! hash_equals($this->fingerprint($booking, $income), $reviewToken)) {
                    $this->fail('The booking or income amounts changed. Reload the review before restoring.');
                }
                foreach (array_unique([$income->payment_date->toDateString(), $projection['payment_date']]) as $date) {
                    $this->periods->assertOpen($booking->villa_id, $date, 'income');
                }
                // Keep the original ID, notes, attachments and audit history. This
                // conversion is committed only if the regular projection restores it.
                $income->forceFill([
                    'booking_id' => $booking->id,
                    'hostex_stay_code' => $booking->hostex_stay_code,
                    'hostex_void_reason' => 'reviewed_restore',
                ])->save();
                $result = $this->incomes->sync($booking);
                $income->refresh();
                if ($result['income_pending'] || $income->trashed()) {
                    throw ValidationException::withMessages(['income' => $booking->hostexIncomeIssueLabel()]);
                }

                return $income;
            });
        });
    }

    private function related(Booking $booking): Builder
    {
        return Income::withTrashed()->where(function ($query) use ($booking) {
            $query->where('booking_id', $booking->id);
            if (filled($booking->hostex_stay_code)) {
                $query->orWhere('hostex_stay_code', $booking->hostex_stay_code);
            }
            if (filled($booking->booking_ref)) {
                $query->orWhere(fn ($ref) => $ref->where('villa_id', $booking->villa_id)
                    ->where('platform_id', $booking->platform_id)->where('booking_ref', $booking->booking_ref));
            }
        });
    }

    private function matches(Booking $booking, Income $income): bool
    {
        return $income->trashed()
            && $income->villa_id === $booking->villa_id && $income->platform_id === $booking->platform_id
            && ($income->booking_id === $booking->id || ($income->booking_id === null
                && filled($booking->booking_ref) && $income->booking_ref === $booking->booking_ref))
            && (! $income->isFromHostex() || $income->hostex_stay_code === $booking->hostex_stay_code);
    }

    private function assertSingleRecord(Collection $related, Income $income): void
    {
        if ($related->contains(fn (Income $other) => $other->id !== $income->id)) {
            $this->fail('Other income entries match this booking. Review them before restoring to avoid duplicate revenue.');
        }
    }

    private function project(Booking $booking, Income $income): array
    {
        if (! config('services.hostex.auto_income_enabled') || config('propertyme.channel_manager_provider') !== 'hostex'
            || $booking->source !== 'hostex' || ! filled($booking->hostex_stay_code) || $booking->hostex_status !== 'accepted') {
            $this->fail('Recovery requires an accepted Hostex booking and enabled automatic income.');
        }
        if ($booking->hostex_rate_amount === null || $booking->hostex_commission_amount === null) {
            $this->fail('Hostex booking value or commission is incomplete.');
        }
        if ($booking->hostex_rate_currency !== 'IDR' || $booking->hostex_commission_currency !== 'IDR') {
            $this->fail('Hostex income needs an IDR amount and commission.');
        }
        if (BigDecimal::of($booking->hostex_commission_amount)->isGreaterThan($booking->hostex_rate_amount)) {
            $this->fail('Hostex commission exceeds the booking value.');
        }
        $basis = config('services.hostex.income_date_basis');
        if (! in_array($basis, ['check_in', 'check_out'], true)) {
            $this->fail('Choose check-in or check-out for the Hostex reporting date.');
        }
        if ($income->fee_basis === FeeBasis::Custom) {
            $this->fail('Custom management fees require a manual income entry.');
        }
        $date = $booking->{$basis}->toDateString();

        return [
            'payment_date' => $date,
            'gross_amount' => $booking->hostex_rate_amount,
            'platform_fee' => $booking->hostex_commission_amount,
            'fee_percentage' => $income->fee_percentage,
            'fee_basis' => $income->fee_basis,
            ...FinanceCalculator::incomeLine($booking->hostex_rate_amount, $booking->hostex_commission_amount, 0, false, $income->fee_percentage, $income->fee_basis),
        ];
    }

    private function fingerprint(Booking $booking, Income $income): string
    {
        return hash('sha256', json_encode([
            $booking->only(['id', 'villa_id', 'platform_id', 'source', 'booking_ref', 'hostex_stay_code', 'hostex_status',
                'check_in', 'check_out', 'hostex_rate_amount', 'hostex_rate_currency', 'hostex_commission_amount', 'hostex_commission_currency']),
            $income->getRawOriginal(), config('services.hostex.income_date_basis'),
        ], JSON_THROW_ON_ERROR));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['income' => __($message)]);
    }
}
