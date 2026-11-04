<?php

namespace Tests\Unit;

use App\Enums\FeeBasis;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceSummary;
use PHPUnit\Framework\TestCase;

class FinanceCalculatorTest extends TestCase
{
    /** Contoh wajib dari spesifikasi — harus cocok persis. */
    public function test_spec_example_final_net_profit_53_450_000(): void
    {
        $line = FinanceCalculator::incomeLine('80000000', '3000000', '0', false, '15', FeeBasis::NetOfPlatform);

        $this->assertSame('77000000.00', $line['net_revenue']);
        $this->assertSame('77000000.00', $line['fee_base_amount']);
        $this->assertSame('11550000.00', $line['fee_amount']);

        $s = new FinanceSummary(
            gross: '80000000.00', platformFee: '3000000.00', otherDeduction: '0.00',
            netRevenue: $line['net_revenue'], managementFee: $line['fee_amount'], expenses: '12000000.00',
        );

        $this->assertSame('65450000.00', $s->revenueAfterFee);
        $this->assertSame('53450000.00', $s->netProfit);
        $this->assertSame('66.8125', $s->margin());
        $this->assertSame('66,8%', $s->marginLabel());
        $this->assertFalse($s->isLoss());
    }

    public function test_gross_zero_margin_is_na_and_no_division_by_zero(): void
    {
        $s = new FinanceSummary(expenses: '500000.00', expenseCount: 1);

        $this->assertNull($s->margin());
        $this->assertSame('N/A', $s->marginLabel());
        $this->assertSame('-500000.00', $s->netProfit, 'Pengeluaran tanpa pemasukan → laba minus');
        $this->assertTrue($s->isLoss());
    }

    public function test_loss_when_expenses_exceed_revenue(): void
    {
        $line = FinanceCalculator::incomeLine('10000000', '1500000', '0', false, '20', FeeBasis::NetOfPlatform);
        $s = new FinanceSummary(gross: '10000000', platformFee: '1500000', netRevenue: $line['net_revenue'], managementFee: $line['fee_amount'], expenses: '9000000');

        $this->assertSame('1700000.00', $line['fee_amount']);
        $this->assertSame('-2200000.00', $s->netProfit);
        $this->assertSame('-22,0%', $s->marginLabel());
    }

    public function test_net_of_platform_does_not_deduct_commission_twice(): void
    {
        // Payout Airbnb 77 jt sudah dipotong komisi 3 jt
        $line = FinanceCalculator::incomeLine('77000000', '3000000', '0', true, '15', FeeBasis::NetOfPlatform);

        $this->assertSame('77000000.00', $line['net_revenue']);
        $this->assertSame('11550000.00', $line['fee_amount']);
        $this->assertSame('0.00', $line['effective_platform_fee']);
    }

    public function test_fee_basis_gross_and_custom(): void
    {
        $gross = FinanceCalculator::incomeLine('80000000', '3000000', '500000', false, '10', FeeBasis::Gross);
        $this->assertSame('76500000.00', $gross['net_revenue']);
        $this->assertSame('8000000.00', $gross['fee_amount']);

        $custom = FinanceCalculator::incomeLine('80000000', '3000000', '0', false, '10', FeeBasis::Custom, '50000000');
        $this->assertSame('50000000.00', $custom['fee_base_amount']);
        $this->assertSame('5000000.00', $custom['fee_amount']);
    }

    public function test_fee_rounding_is_precise_without_float_errors(): void
    {
        $line = FinanceCalculator::incomeLine('1234567.89', '0', '0', false, '12.5', FeeBasis::Gross);
        $this->assertSame('154320.99', $line['fee_amount']); // 154320.98625 → HALF_UP

        $this->assertSame('0.30', FinanceCalculator::sum(['0.10', '0.20']));
    }

    public function test_growth(): void
    {
        $this->assertSame(25.0, FinanceCalculator::growth('125', '100'));
        $this->assertSame(-50.0, FinanceCalculator::growth('50', '100'));
        $this->assertNull(FinanceCalculator::growth('100', '0'));
        $this->assertSame(200.0, FinanceCalculator::growth('100', '-100'), 'dari minus ke plus');
    }
}
