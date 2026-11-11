<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ExpenseStatus;
use App\Enums\ReportStatus;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Models\Villa;
use App\Notifications\ReportFinalizedNotification;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use App\Support\Notify;
use App\Support\Rupiah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyusun data laporan bulanan/tahunan — satu sumber untuk tampilan web, PDF, dan Excel.
 * Owner hanya mendapat bulan yang laporannya sudah Final.
 */
class ReportService
{
    public function __construct(private FinanceCalculator $calc) {}

    public function build(User $user, ?int $villaId, Carbon $from, Carbon $to, string $type): array
    {
        $filter = FinanceFilter::for($user, $from, $to, $villaId);
        $villas = $filter->villas()->with('owner:id,name,email,phone')->orderBy('name')->get();

        // Owner: batasi ke bulan Final
        $scope = null;
        if (! $user->isManager()) {
            $scope = $this->finalMonths($villas->pluck('id')->all(), $from, $to);
            $filter = $filter->withMonthScope($scope);
        }

        $summary = $this->calc->summary($filter);

        $incomes = $this->calc->incomeQuery($filter)
            ->with(['villa:id,name', 'platform:id,name', 'booking:id,guest_name,check_in,check_out'])
            ->orderBy('payment_date')->orderBy('id')->get();

        $expenses = $this->calc->expenseQuery($filter)
            ->with(['villa:id,name', 'category:id,name', 'receipts:id,expense_id'])
            ->orderBy('expense_date')->orderBy('id')->get();

        $statuses = MonthlyReport::query()->whereIn('villa_id', $villas->pluck('id'))
            ->where(fn ($q) => $q->where('year', '>', $from->year)->orWhere(fn ($x) => $x->where('year', $from->year)->where('month', '>=', $from->month)))
            ->where(fn ($q) => $q->where('year', '<', $to->year)->orWhere(fn ($x) => $x->where('year', $to->year)->where('month', '<=', $to->month)))
            ->with('finalizer:id,name')->get();

        return [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $type === 'annual' ? __('Tahun :year', ['year' => $from->year]) : $from->translatedFormat('F Y'),
            'villa' => $villaId ? $villas->first() : null,
            'villas' => $villas,
            'summary' => $summary,
            'byPlatform' => $this->calc->byPlatform($filter),
            'byCategory' => $this->calc->byCategory($filter),
            'byVilla' => $villaId ? collect() : $this->calc->byVilla($filter),
            'series' => $type === 'annual' ? $this->calc->series($filter, 'month') : collect(),
            'incomes' => $incomes,
            'expenses' => $expenses,
            'feeInfo' => $this->feeInfo($villas, $incomes),
            'statuses' => $statuses,
            'monthStatus' => $type === 'monthly' && $villaId ? $statuses->first() : null,
            'restricted' => $scope !== null,
            'scope' => $scope,
            'hasFinal' => $scope === null || collect($scope)->flatten()->isNotEmpty(),
            'generatedAt' => now(),
            'generatedBy' => $user,
        ];
    }

    /** @return array<int, string[]> villa_id → ['2026-09', ...] yang Final */
    public function finalMonths(array $villaIds, Carbon $from, Carbon $to): array
    {
        $scope = array_fill_keys($villaIds, []);
        MonthlyReport::query()->whereIn('villa_id', $villaIds)->where('status', ReportStatus::Final->value)
            ->get(['villa_id', 'year', 'month'])
            ->each(function ($r) use (&$scope, $from, $to) {
                $d = Carbon::create($r->year, $r->month, 1);
                if ($d->between($from->copy()->startOfMonth(), $to->copy()->endOfMonth())) {
                    $scope[$r->villa_id][] = $d->format('Y-m');
                }
            });

        return $scope;
    }

    /** Persentase & basis fee yang dipakai (dari snapshot pemasukan; fallback fee villa saat ini). */
    private function feeInfo(Collection $villas, Collection $incomes): Collection
    {
        return $villas->mapWithKeys(function (Villa $v) use ($incomes) {
            $used = $incomes->where('villa_id', $v->id)
                ->map(fn ($i) => __(':percent dari :basis', ['percent' => Rupiah::percent($i->fee_percentage), 'basis' => app()->getLocale() === 'de' ? $i->fee_basis->label() : mb_strtolower($i->fee_basis->label())]))
                ->unique()->values();

            return [$v->id => $used->isNotEmpty() ? $used->join('; ') : $v->feeLabel().' '.__('(fee saat ini)')];
        });
    }

    /** Hal yang harus beres sebelum bulan boleh Final. */
    public function blockers(Villa $villa, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1);
        $to = $from->copy()->endOfMonth();
        $out = [];

        $pending = Expense::where('villa_id', $villa->id)->whereIn('status', [ExpenseStatus::Pending->value, ExpenseStatus::Returned->value])
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->count();
        if ($pending) {
            $out[] = __(':count nota belum Valid (menunggu pemeriksaan / dikembalikan).', ['count' => $pending]);
        }
        if (config('services.hostex.auto_income_enabled')) {
            $pendingIncome = HostexIncomeService::pendingQuery([$villa->id], $from->toDateString(), $to->toDateString())->count();
            if ($pendingIncome) {
                $out[] = __(':count Hostex bookings need income review before finalizing.', ['count' => $pendingIncome]);
            }
        }
        if ($from->isFuture()) {
            $out[] = __('Bulan ini belum dimulai.');
        }

        return $out;
    }

    /** Peringatan (tidak memblokir). */
    public function warnings(Villa $villa, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1);
        $to = $from->copy()->endOfMonth();
        $out = [];
        $incomplete = Booking::where('villa_id', $villa->id)->where('status', BookingStatus::NeedsCompletion->value)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])->count();
        if ($incomplete) {
            $out[] = __(':count reservasi di bulan ini masih "Perlu dilengkapi" — pastikan pemasukannya sudah dicatat.', ['count' => $incomplete]);
        }
        if ($to->isFuture()) {
            $out[] = __('Bulan ini belum berakhir.');
        }

        return $out;
    }

    public function finalize(Villa $villa, int $year, int $month, User $manager): MonthlyReport
    {
        if ($b = $this->blockers($villa, $year, $month)) {
            throw ValidationException::withMessages(['report' => __('Belum bisa Final: :reasons', ['reasons' => implode(' ', $b)])]);
        }

        $report = DB::transaction(function () use ($villa, $year, $month, $manager) {
            $report = MonthlyReport::firstOrNew(['villa_id' => $villa->id, 'year' => $year, 'month' => $month]);
            $report->fill([
                'status' => ReportStatus::Final,
                'finalized_at' => now(),
                'finalized_by' => $manager->id,
                'revision' => ($report->revision ?? 0) + 1,
            ])->save();

            return $report;
        });

        if ($villa->owner) {
            Notify::send([$villa->owner], new ReportFinalizedNotification($report));
        }

        return $report;
    }

    /** Buka kunci — alasan wajib & tercatat di audit log. */
    public function unlock(MonthlyReport $report, User $manager, string $reason): MonthlyReport
    {
        $report->update([
            'status' => ReportStatus::Draft,
            'unlocked_at' => now(),
            'unlocked_by' => $manager->id,
            'unlock_reason' => $reason,
        ]);

        return $report;
    }
}
