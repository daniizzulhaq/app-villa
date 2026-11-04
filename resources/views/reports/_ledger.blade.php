{{-- Rincian rumus keuangan. $s: FinanceSummary --}}
<table class="pm-ledger">
    <tr><td>{{ __('Gross booking income') }}</td><td>@rupiah($s->gross)</td></tr>
    <tr class="sub minus"><td>{{ __('Komisi platform') }}</td><td>@rupiah($s->platformFee)</td></tr>
    <tr class="sub minus"><td>{{ __('Potongan lain') }}</td><td>@rupiah($s->otherDeduction)</td></tr>
    <tr><td><strong>{{ __('Net booking revenue') }}</strong></td><td>@rupiah($s->netRevenue)</td></tr>
    <tr class="sub minus"><td>{{ __('Management fee') }}</td><td>@rupiah($s->managementFee)</td></tr>
    <tr><td><strong>{{ __('Revenue after fee') }}</strong></td><td>@rupiah($s->revenueAfterFee)</td></tr>
    <tr class="sub minus"><td>{{ __('Total pengeluaran (Valid)') }}</td><td>@rupiah($s->expenses)</td></tr>
    <tr class="total"><td>{{ __('Final net profit') }}</td><td class="{{ $s->isLoss() ? 'text-negative' : 'text-positive' }}">@rupiah($s->netProfit)</td></tr>
    <tr class="sub"><td>{{ __('Net profit margin') }}</td><td>{{ $s->marginLabel() }}</td></tr>
</table>
