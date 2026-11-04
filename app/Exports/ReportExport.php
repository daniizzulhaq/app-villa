<?php

namespace App\Exports;

use App\Services\Finance\FinanceCalculator as F;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Laporan keuangan ke Excel. Data berasal dari ReportService (sama persis dengan web & PDF). */
class ReportExport implements Export, WithMultipleSheets
{
    public function __construct(private array $d) {}

    public function sheets(): array
    {
        $d = $this->d;
        $s = $d['summary'];
        $n = fn ($v) => F::toFloat((string) $v);

        $summaryRows = [
            [__('PropertyMe — Laporan Keuangan Villa')],
            [__('Villa'), $d['villa']?->name ?? __('Semua villa (:count)', ['count' => $d['villas']->count()])],
            [__('Owner'), $d['villa']?->owner?->name ?? '—'],
            [__('Periode'), $d['periodLabel']],
            [__('Dibuat'), $d['generatedAt']->translatedFormat('d M Y H:i').' · '.__('oleh :name', ['name' => $d['generatedBy']->name])],
            [],
            [__('Ringkasan'), __('Nominal (Rp)')],
            [__('Gross Booking Income'), $n($s->gross)],
            [__('Komisi Platform'), -$n($s->platformFee)],
            [__('Potongan Lain'), -$n($s->otherDeduction)],
            [__('Net Booking Revenue'), $n($s->netRevenue)],
            [__('Management Fee'), -$n($s->managementFee)],
            [__('Revenue After Fee'), $n($s->revenueAfterFee)],
            [__('Total Pengeluaran (Valid)'), -$n($s->expenses)],
            [__('FINAL NET PROFIT'), $n($s->netProfit)],
            [__('Net Profit Margin'), $s->marginLabel()],
            [],
            [__('Basis management fee')],
        ];
        foreach ($d['feeInfo'] as $villaId => $label) {
            $summaryRows[] = [$d['villas']->firstWhere('id', $villaId)?->name, $label];
        }
        $summaryRows[] = [];
        $summaryRows[] = [__('Pemasukan per platform'), __('Gross'), 'Net', 'Fee'];
        foreach ($d['byPlatform'] as $p) {
            $summaryRows[] = [$p->name, $n($p->gross), $n($p->net_revenue), $n($p->fee_amount)];
        }
        $summaryRows[] = [];
        $summaryRows[] = [__('Pengeluaran per kategori'), __('Total'), __('Jumlah transaksi')];
        foreach ($d['byCategory'] as $c) {
            $summaryRows[] = [__($c->name), $n($c->total), (int) $c->cnt];
        }
        if ($d['series']->isNotEmpty()) {
            $summaryRows[] = [];
            $summaryRows[] = [__('Bulan'), __('Gross'), __('Komisi'), __('Management Fee'), __('Pengeluaran'), __('Net Profit')];
            foreach ($d['series'] as $row) {
                $x = $row['summary'];
                $summaryRows[] = [$row['label'], $n($x->gross), $n(F::sum([$x->platformFee, $x->otherDeduction])), $n($x->managementFee), $n($x->expenses), $n($x->netProfit)];
            }
        }

        $incomeRows = $d['incomes']->map(fn ($i) => [
            $i->payment_date->format('Y-m-d'), $i->villa->name, $i->platform->name, $i->booking_ref, $i->booking?->guest_name,
            $n($i->gross_amount), $n($i->is_net_of_platform ? 0 : $i->platform_fee), $n($i->other_deduction), $n($i->net_revenue),
            (float) $i->fee_percentage, $i->fee_basis->label(), $n($i->fee_amount), $n(F::sum([$i->net_revenue, '-'.$i->fee_amount])),
            $i->is_net_of_platform ? 'Ya' : __('Tidak'), $i->notes,
        ])->all();

        $expenseRows = $d['expenses']->map(fn ($e) => [
            $e->expense_date->format('Y-m-d'), $e->villa->name, __($e->category->name), $e->description, $e->vendor,
            $e->payment_method?->label(), $n($e->amount), $e->receipts->count().' foto', 'NOTA-'.str_pad((string) $e->id, 5, '0', STR_PAD_LEFT),
        ])->all();

        return [
            new Sheet(__('Ringkasan'), [], $summaryRows, ['B' => '#,##0;[Red]-#,##0', 'C' => '#,##0', 'D' => '#,##0', 'E' => '#,##0', 'F' => '#,##0;[Red]-#,##0'], titleRow: true),
            new Sheet(__('Pemasukan'), [__('Tanggal bayar'), __('Villa'), __('Platform'), __('Kode booking'), __('Tamu'), __('Gross'), __('Komisi'), __('Potongan lain'), __('Net revenue'), __('Fee %'), __('Basis fee'), __('Management fee'), __('Revenue after fee'), __('Sudah net?'), __('Catatan')],
                $incomeRows, ['F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0', 'I' => '#,##0', 'L' => '#,##0', 'M' => '#,##0']),
            new Sheet(__('Pengeluaran'), [__('Tanggal'), __('Villa'), __('Kategori'), __('Keterangan'), __('Vendor'), __('Metode bayar'), __('Nominal'), __('Foto nota'), __('Ref. nota')],
                $expenseRows, ['G' => '#,##0']),
        ];
    }
}
