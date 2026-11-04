<?php

namespace App\Services\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\FeeBasis;
use App\Models\Expense;
use App\Models\Income;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA tempat rumus keuangan. Dashboard, grafik, laporan, PDF & Excel semuanya memanggil kelas ini,
 * dan selalu menjumlahkan dari tabel transaksi (incomes, expenses) — tidak pernah dari total yang diketik.
 */
class FinanceCalculator
{
    /* ===================== Rumus per transaksi ===================== */

    /**
     * Hitung snapshot satu pemasukan.
     * - is_net_of_platform: nominal yang diisi sudah dipotong komisi → komisi TIDAK dipotong lagi.
     * - Basis fee: gross | net_of_platform | custom (nominal basis diisi Manager).
     *
     * @return array{net_revenue:string, fee_base_amount:string, fee_amount:string, effective_platform_fee:string}
     */
    public static function incomeLine(
        string|int|float $gross,
        string|int|float|null $platformFee,
        string|int|float|null $otherDeduction,
        bool $isNetOfPlatform,
        string|int|float $feePercentage,
        FeeBasis $basis,
        string|int|float|null $customBase = null,
    ): array {
        $g = BigDecimal::of((string) $gross);
        $pf = $isNetOfPlatform ? BigDecimal::zero() : BigDecimal::of((string) ($platformFee ?? 0));
        $od = BigDecimal::of((string) ($otherDeduction ?? 0));
        $net = $g->minus($pf)->minus($od);

        $base = match ($basis) {
            FeeBasis::Gross => $g,
            FeeBasis::NetOfPlatform => $net,
            FeeBasis::Custom => BigDecimal::of((string) ($customBase ?? $net)),
        };

        $fee = $base->multipliedBy((string) $feePercentage)->dividedBy(100, 2, RoundingMode::HALF_UP);

        return [
            'net_revenue' => (string) $net->toScale(2),
            'fee_base_amount' => (string) $base->toScale(2),
            'fee_amount' => (string) $fee,
            'effective_platform_fee' => (string) $pf->toScale(2),
        ];
    }

    /* ===================== Agregasi dari database ===================== */

    public function summary(FinanceFilter $f): FinanceSummary
    {
        return FinanceSummary::fromSums(
            $this->incomeQuery($f)->selectRaw($this->incomeSums())->toBase()->first(),
            $this->expenseQuery($f)->selectRaw('SUM(amount) as total, COUNT(*) as cnt')->toBase()->first(),
        );
    }

    /**
     * Ringkasan per periode. $granularity: month | quarter | year.
     * @return Collection<string, array{label:string, from:Carbon, to:Carbon, summary:FinanceSummary}>
     */
    public function series(FinanceFilter $f, string $granularity = 'month'): Collection
    {
        [$incExpr, $expExpr] = match ($granularity) {
            'year' => ["YEAR(payment_date)", "YEAR(expense_date)"],
            'quarter' => ["CONCAT(YEAR(payment_date), '-Q', QUARTER(payment_date))", "CONCAT(YEAR(expense_date), '-Q', QUARTER(expense_date))"],
            default => ["DATE_FORMAT(payment_date, '%Y-%m')", "DATE_FORMAT(expense_date, '%Y-%m')"],
        };

        $inc = $this->incomeQuery($f)->selectRaw("$incExpr as k, ".$this->incomeSums())->groupBy('k')->toBase()->get()->keyBy('k');
        $exp = $this->expenseQuery($f)->selectRaw("$expExpr as k, SUM(amount) as total, COUNT(*) as cnt")->groupBy('k')->toBase()->get()->keyBy('k');

        $out = collect();
        $cursor = $f->from->copy();
        while ($cursor->lte($f->to)) {
            [$key, $label, $start, $end] = match ($granularity) {
                'year' => [(string) $cursor->year, (string) $cursor->year, $cursor->copy()->startOfYear(), $cursor->copy()->endOfYear()],
                'quarter' => [$cursor->year.'-Q'.$cursor->quarter, 'Q'.$cursor->quarter.' '.$cursor->year, $cursor->copy()->startOfQuarter(), $cursor->copy()->endOfQuarter()],
                default => [$cursor->format('Y-m'), $cursor->translatedFormat('M Y'), $cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth()],
            };
            $out->put($key, [
                'key' => $key,
                'label' => $label,
                'from' => $start->max($f->from)->startOfDay(),
                'to' => $end->min($f->to)->startOfDay(),
                'summary' => FinanceSummary::fromSums($inc->get($key), $exp->get($key)),
            ]);
            $cursor = match ($granularity) {
                'year' => $cursor->copy()->startOfYear()->addYear(),
                'quarter' => $cursor->copy()->startOfQuarter()->addQuarter(),
                default => $cursor->copy()->startOfMonth()->addMonth(),
            };
        }

        return $out;
    }

    /** Pemasukan per platform: [platform_id => [name, color, gross, net, fee, count]] */
    public function byPlatform(FinanceFilter $f): Collection
    {
        return $this->incomeQuery($f)
            ->join('booking_platforms as bp', 'bp.id', '=', 'incomes.platform_id')
            ->selectRaw('bp.id, bp.name, bp.color, '.$this->incomeSums())
            ->groupBy('bp.id', 'bp.name', 'bp.color')->orderBy('bp.sort_order')
            ->toBase()->get();
    }

    /** Pengeluaran Valid per kategori, terbesar dulu. */
    public function byCategory(FinanceFilter $f): Collection
    {
        return $this->expenseQuery($f)
            ->join('expense_categories as ec', 'ec.id', '=', 'expenses.category_id')
            ->selectRaw('ec.id, ec.name, SUM(expenses.amount) as total, COUNT(*) as cnt')
            ->groupBy('ec.id', 'ec.name')->orderByDesc('total')
            ->toBase()->get();
    }

    /** Ringkasan per villa: [villa_id => FinanceSummary] */
    public function byVilla(FinanceFilter $f): Collection
    {
        $inc = $this->incomeQuery($f)->selectRaw('villa_id, '.$this->incomeSums())->groupBy('villa_id')->toBase()->get()->keyBy('villa_id');
        $exp = $this->expenseQuery($f)->selectRaw('villa_id, SUM(amount) as total, COUNT(*) as cnt')->groupBy('villa_id')->toBase()->get()->keyBy('villa_id');

        return $f->villas()->orderBy('name')->get(['id', 'name', 'fee_percentage', 'fee_basis'])
            ->mapWithKeys(fn ($v) => [$v->id => ['villa' => $v, 'summary' => FinanceSummary::fromSums($inc->get($v->id), $exp->get($v->id))]]);
    }

    /* ===================== Query dasar (dipakai juga oleh laporan) ===================== */

    public function incomeQuery(FinanceFilter $f): Builder
    {
        return Income::query()
            ->when($f->villaIds !== null, fn ($q) => $q->whereIn('incomes.villa_id', $f->villaIds ?: [0]))
            ->whereBetween('incomes.payment_date', [$f->from->toDateString(), $f->to->toDateString()])
            ->when($f->platformId, fn ($q, $p) => $q->where('incomes.platform_id', $p))
            ->when($f->monthScope !== null, fn ($q) => $this->applyMonthScope($q, $f->monthScope, 'incomes.villa_id', 'incomes.payment_date'));
    }

    /** Hanya pasangan villa–bulan yang diizinkan (mis. laporan Final untuk Owner). */
    private function applyMonthScope(Builder $q, array $scope, string $villaCol, string $dateCol): void
    {
        $q->where(function ($w) use ($scope, $villaCol, $dateCol) {
            $w->whereRaw('1 = 0');
            foreach ($scope as $villaId => $months) {
                if ($months) {
                    $w->orWhere(fn ($x) => $x->where($villaCol, $villaId)
                        ->whereIn(DB::raw("DATE_FORMAT($dateCol, '%Y-%m')"), array_values($months)));
                }
            }
        });
    }

    /** Hanya pengeluaran berstatus Valid. Filter sumber booking tidak berlaku untuk pengeluaran. */
    public function expenseQuery(FinanceFilter $f): Builder
    {
        return Expense::query()
            ->where('expenses.status', ExpenseStatus::Valid->value)
            ->when($f->villaIds !== null, fn ($q) => $q->whereIn('expenses.villa_id', $f->villaIds ?: [0]))
            ->whereBetween('expenses.expense_date', [$f->from->toDateString(), $f->to->toDateString()])
            ->when($f->monthScope !== null, fn ($q) => $this->applyMonthScope($q, $f->monthScope, 'expenses.villa_id', 'expenses.expense_date'));
    }

    /** Komisi yang sudah termasuk di nominal (is_net_of_platform) tidak dihitung dua kali. */
    private function incomeSums(): string
    {
        return 'SUM(incomes.gross_amount) as gross, '
            .'SUM(CASE WHEN incomes.is_net_of_platform = 1 THEN 0 ELSE incomes.platform_fee END) as platform_fee, '
            .'SUM(incomes.other_deduction) as other_deduction, '
            .'SUM(incomes.net_revenue) as net_revenue, '
            .'SUM(incomes.fee_amount) as fee_amount, '
            .'COUNT(*) as cnt';
    }

    /* ===================== Helper perbandingan ===================== */

    /** Pertumbuhan % dari $previous ke $current; null jika pembanding 0. */
    public static function growth(string $current, string $previous): ?float
    {
        $p = BigDecimal::of($previous);
        if ($p->isZero()) {
            return null;
        }

        return (float) (string) BigDecimal::of($current)->minus($p)->multipliedBy(100)->dividedBy($p->abs(), 1, RoundingMode::HALF_UP);
    }

    public static function sum(iterable $values): string
    {
        $t = BigDecimal::zero();
        foreach ($values as $v) {
            $t = $t->plus((string) ($v ?? 0));
        }

        return (string) $t->toScale(2);
    }

    public static function toFloat(string|null $v): float
    {
        return (float) (string) BigDecimal::of($v ?? '0')->toScale(0, RoundingMode::HALF_UP);
    }
}
