<?php

namespace App\Services\Finance;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Hasil perhitungan keuangan untuk satu periode/filter. Semua nominal string desimal (presisi penuh).
 *   Net Booking Revenue = Gross − Komisi Platform − Potongan Lain
 *   Revenue After Fee   = Net Booking Revenue − Management Fee
 *   Final Net Profit    = Revenue After Fee − Total Pengeluaran Valid
 *   Net Profit Margin   = Final Net Profit ÷ Gross × 100%   (Gross = 0 → null / "N/A")
 */
final class FinanceSummary
{
    public readonly string $revenueAfterFee;

    public readonly string $netProfit;

    public function __construct(
        public readonly string $gross = '0.00',
        public readonly string $platformFee = '0.00',
        public readonly string $otherDeduction = '0.00',
        public readonly string $netRevenue = '0.00',
        public readonly string $managementFee = '0.00',
        public readonly string $expenses = '0.00',
        public readonly int $incomeCount = 0,
        public readonly int $expenseCount = 0,
    ) {
        $this->revenueAfterFee = (string) BigDecimal::of($netRevenue)->minus($managementFee)->toScale(2);
        $this->netProfit = (string) BigDecimal::of($this->revenueAfterFee)->minus($expenses)->toScale(2);
    }

    public static function fromSums(?object $income, ?object $expense): self
    {
        $d = fn ($v) => (string) BigDecimal::of($v ?? '0')->toScale(2, RoundingMode::HALF_UP);

        return new self(
            gross: $d($income->gross ?? null),
            platformFee: $d($income->platform_fee ?? null),
            otherDeduction: $d($income->other_deduction ?? null),
            netRevenue: $d($income->net_revenue ?? null),
            managementFee: $d($income->fee_amount ?? null),
            expenses: $d($expense->total ?? null),
            incomeCount: (int) ($income->cnt ?? 0),
            expenseCount: (int) ($expense->cnt ?? 0),
        );
    }

    /** Margin dalam persen (string 4 desimal) atau null bila Gross = 0. */
    public function margin(): ?string
    {
        if (BigDecimal::of($this->gross)->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($this->netProfit)->multipliedBy(100)->dividedBy($this->gross, 4, RoundingMode::HALF_UP);
    }

    /** "66,8%" atau "N/A" */
    public function marginLabel(): string
    {
        $m = $this->margin();

        return $m === null ? 'N/A' : number_format((float) $m, 1, ',', '.').'%';
    }

    public function isLoss(): bool
    {
        return BigDecimal::of($this->netProfit)->isNegative();
    }

    public function isEmpty(): bool
    {
        return $this->incomeCount === 0 && $this->expenseCount === 0;
    }

    public function toArray(): array
    {
        return [
            'gross' => $this->gross, 'platform_fee' => $this->platformFee, 'other_deduction' => $this->otherDeduction,
            'net_revenue' => $this->netRevenue, 'management_fee' => $this->managementFee,
            'revenue_after_fee' => $this->revenueAfterFee, 'expenses' => $this->expenses,
            'net_profit' => $this->netProfit, 'margin' => $this->margin(),
        ];
    }
}
