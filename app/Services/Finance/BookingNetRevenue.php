<?php

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Enums\FeeBasis;
use App\Models\Booking;
use App\Support\Rupiah;

/** Calendar preview only; does not create or change financial ledger entries. */
class BookingNetRevenue
{
    public function display(Booking $booking): array
    {
        if ((int) ($booking->incomes_count ?? 0) > 0 && $booking->incomes_sum_net_revenue !== null) {
            return [
                'formatted' => Rupiah::format($booking->incomes_sum_net_revenue),
                'hint' => __('Dari pemasukan tercatat, sebelum management fee.'),
            ];
        }
        if (config('services.hostex.auto_income_enabled') && $booking->source === 'hostex' && $booking->status === BookingStatus::Cancelled) {
            return ['formatted' => null, 'hint' => __('Cancelled booking; automatic revenue is excluded.')];
        }
        if (config('services.hostex.auto_income_enabled') && $booking->source === 'hostex' && $booking->hostex_income_error) {
            return ['formatted' => null, 'hint' => $booking->hostexIncomeIssueLabel()];
        }
        if ($booking->source === 'hostex' && $booking->hostex_rate_amount !== null
            && $booking->hostex_commission_amount !== null
            && preg_match('/^[A-Z]{3}$/', (string) $booking->hostex_rate_currency)
            && $booking->hostex_rate_currency === $booking->hostex_commission_currency) {
            $line = FinanceCalculator::incomeLine(
                $booking->hostex_rate_amount, $booking->hostex_commission_amount, 0,
                false, 0, FeeBasis::NetOfPlatform,
            );
            $amount = $line['net_revenue'];
            $currency = $booking->hostex_rate_currency;

            return [
                'formatted' => $currency === 'IDR' ? Rupiah::format($amount) : $currency.' '.number_format((float) $amount, 2, '.', ','),
                'hint' => __('Setelah komisi OTA, sebelum management fee.'),
            ];
        }

        return ['formatted' => null, 'hint' => __('Data pendapatan belum lengkap.')];
    }
}
