@php
    use App\Support\Rupiah;
    use App\Services\Finance\FinanceCalculator as F;
    $s = $summary;
    $section = 0;
    $compactOverview = $byCategory->count() <= 4 && $feeInfo->count() <= 2;
    $denseTables = $incomes->contains(fn ($income) => mb_strlen(Rupiah::format($income->gross_amount)) > 17)
        || $expenses->contains(fn ($expense) => mb_strlen(Rupiah::format($expense->amount)) > 17)
        || mb_strlen(Rupiah::format($s->gross)) > 19;
    $tableMoney = fn ($value) => Rupiah::format($value, ! $denseTables);
    $reportTitle = $type === 'annual' ? __('Laporan Tahunan') : __('Laporan Bulanan');
    $reportRef = 'PM-'.$from->format($type === 'annual' ? 'Y' : 'Ym').'-'.($villa ? 'V'.str_pad((string) $villa->id, 4, '0', STR_PAD_LEFT) : 'ALL');
    $logoPath = public_path('images/propertyme-logo.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    $moneyClass = fn ($value) => mb_strlen(Rupiah::format($value)) > 19 ? ' amount-small' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ $reportTitle }} · {{ $periodLabel }} · PropertyMe</title>
<style>
    @page { margin: 92pt 36pt 54pt; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { margin: 0; color: #202020; font-size: 8.3pt; line-height: 1.45; }
    header { position: fixed; top: -72pt; left: 0; right: 0; height: 54pt; border-bottom: 1.2pt solid #b68a2c; }
    footer { position: fixed; bottom: -32pt; left: 0; right: 0; height: 18pt; padding-top: 7pt; border-top: .5pt solid #ded5c4; color: #707070; font-size: 6.5pt; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .brand-logo { width: 78pt; height: 42pt; }
    .brand-caption { margin-top: -1pt; color: #454545; font-size: 5.2pt; letter-spacing: 1.5pt; }
    .brand-name { font-size: 20pt; font-weight: bold; }
    .header-meta { text-align: right; padding-top: 2pt; }
    .eyebrow { color: #9b7013; font-size: 7pt; letter-spacing: 1.4pt; text-transform: uppercase; }
    .header-period { color: #202020; font-size: 11pt; font-weight: bold; margin: 2pt 0; }
    .reference { color: #707070; font-size: 6.5pt; letter-spacing: .5pt; }
    .cover { margin-bottom: 10pt; }
    h1 { margin: 3pt 0 5pt; color: #202020; font-size: 23pt; line-height: 1.15; letter-spacing: -.7pt; font-weight: bold; }
    .period { color: #454545; font-size: 9pt; }
    .badge { display: inline-block; padding: 2pt 7pt; margin-left: 7pt; font-size: 6.5pt; font-weight: bold; letter-spacing: .8pt; border: .6pt solid #ded5c4; color: #454545; }
    .badge-final { border-color: #b68a2c; background: #fbf1d9; color: #7c570d; }
    .metadata { margin-top: 8pt; border-top: .5pt solid #ece9e3; border-bottom: .5pt solid #ece9e3; }
    .metadata td { width: 50%; padding: 6pt 0; }
    .metadata td + td { padding-left: 16pt; }
    .label { color: #707070; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .65pt; margin-bottom: 3pt; }
    .meta-value { color: #202020; font-size: 8.3pt; }
    .muted { color: #707070; }
    .small { font-size: 7pt; }
    .subtext { display: block; margin-top: 2pt; font-size: 6.6pt; color: #707070; white-space: normal; line-height: 1.4; }
    .hero { margin-top: 8pt; background: #202020; border-left: 4pt solid #b68a2c; color: #ffffff; page-break-inside: avoid; }
    .hero td { padding: 10pt 16pt; }
    .hero-label { font-size: 7pt; color: #ffffff; letter-spacing: .8pt; text-transform: uppercase; }
    .hero-amount { margin-top: 4pt; font-size: 25pt; font-weight: bold; line-height: 1.2; color: #ffffff; }
    .hero-margin { width: 25%; text-align: right; border-left: .5pt solid #454545; }
    .hero-margin .value { margin-top: 4pt; font-size: 19pt; color: #dfbd67; }
    .kpi { margin: 8pt 0 10pt; page-break-inside: avoid; }
    .kpi td { width: 33.333%; padding: 8pt 11pt; border: .5pt solid #ded5c4; }
    .kpi .value { color: #202020; font-size: 13pt; font-weight: bold; line-height: 1.3; margin-top: 4pt; white-space: nowrap; }
    .kpi .amount-small { font-size: 10.5pt; }
    .hero-amount.amount-small { font-size: 20pt; }
    .section-heading { margin: 13pt 0 7pt; padding-bottom: 5pt; border-bottom: .7pt solid #ded5c4; color: #202020; font-size: 10pt; line-height: 1.4; page-break-after: avoid; }
    .section-heading span { color: #9b7013; margin-right: 7pt; font-size: 8pt; }
    .section-note { margin: -2pt 0 8pt; color: #707070; font-size: 7pt; page-break-after: avoid; }
    .ledger td { padding: 4pt 9pt; border-bottom: .5pt solid #ece9e3; }
    .ledger .deduction td { color: #707070; padding-left: 19pt; }
    .ledger .subtotal td { background: #f8f8f8; font-weight: bold; }
    .ledger .total td { background: #fbf1d9; border-top: 1pt solid #b68a2c; border-bottom: 1pt solid #b68a2c; font-weight: bold; font-size: 10pt; padding-top: 8pt; padding-bottom: 8pt; }
    .n { text-align: right; white-space: nowrap; }
    .ledger .n { width: 34%; }
    .overview-left { width: 60%; padding-right: 12pt; }
    .overview-right { width: 40%; padding-left: 10pt; }
    .overview-right .note { margin-top: 17pt; }
    .grid { table-layout: fixed; margin-bottom: 6pt; font-size: {{ $denseTables ? '6' : '7.4' }}pt; }
    .grid thead { display: table-header-group; }
    .grid tbody { display: table-row-group; }
    .grid tfoot { display: table-row-group; }
    .grid tr { page-break-inside: avoid; }
    .grid th { padding: 6pt 5pt; background: #202020; border-bottom: 1.5pt solid #b68a2c; color: #ffffff; font-size: 6pt; font-weight: bold; letter-spacing: .2pt; text-align: left; vertical-align: middle; }
    .grid td { padding: 4pt; border-bottom: .5pt solid #ece9e3; word-wrap: break-word; }
    .grid tbody tr:nth-child(even) td { background: #fafafa; }
    .grid tbody tr:last-child { page-break-after: avoid; }
    .grid th.n, .grid td.n { text-align: right; }
    .grid .n .subtext { white-space: normal; }
    .grid .subtext { font-size: {{ $denseTables ? '6' : '6.6' }}pt; }
    .grid tfoot td { background: #fbf1d9; border-top: 1pt solid #b68a2c; border-bottom: .5pt solid #ded5c4; font-weight: bold; padding-top: 7pt; padding-bottom: 7pt; }
    .note { padding: 9pt 11pt; margin: 10pt 0; background: #fbf1d9; border-left: 2pt solid #b68a2c; color: #454545; font-size: 7pt; }
    .note .label { color: #7c570d; }
    .note p { margin: 2pt 0; }
    .footnote { color: #707070; font-size: 6.7pt; margin: 4pt 0 6pt; }
    .page-break { page-break-before: always; }
    .keep-together { page-break-inside: avoid; }
    .empty { margin: 22pt 0; padding: 24pt; border: .6pt solid #ded5c4; border-top: 2pt solid #b68a2c; background: #fafafa; }
    .empty h2 { margin: 0 0 6pt; font-size: 14pt; color: #202020; }
    .empty p { color: #707070; margin: 0; }
</style>
</head>
<body>
<header>
    <table><tr>
        <td>
            @if ($logoData)<img class="brand-logo" src="{{ $logoData }}" alt="PropertyMe">@else<div class="brand-name">PropertyMe</div>@endif
            <div class="brand-caption">VILLA MANAGEMENT</div>
        </td>
        <td class="header-meta">
            <div class="eyebrow">{{ $reportTitle }}</div>
            <div class="header-period">{{ $periodLabel }}</div>
            <div class="reference">{{ $reportRef }}</div>
        </td>
    </tr></table>
</header>
<footer>
    <table><tr>
        <td style="width: 50%">PropertyMe · {{ $reportRef }}</td>
        <td>{{ __('Dibuat') }} {{ $generatedAt->translatedFormat('d M Y H:i') }}</td>
        <td style="width: 100pt"></td>
    </tr></table>
</footer>
<main>
    <div class="cover">
        <div class="eyebrow">{{ __('Financial statement') }} · IDR</div>
        <h1>{{ $villa?->name ?? __('Semua Villa') }}</h1>
        <div class="period">{{ $from->translatedFormat('d F Y') }} – {{ $to->translatedFormat('d F Y') }}
            @if ($type === 'monthly' && $villa)
                @php $isFinal = $monthStatus?->status === \App\Enums\ReportStatus::Final; @endphp
                <span class="badge {{ $isFinal ? 'badge-final' : '' }}">{{ $isFinal ? __('Final') : __('Draft') }}</span>
            @endif
        </div>
        <table class="metadata"><tr>
            <td><div class="label">{{ $villa ? __('Owner') : __('Villa') }}</div>
                <div class="meta-value">{{ $villa ? ($villa->owner?->name ?? '—') : __('Gabungan :count villa', ['count' => $villas->count()]) }}</div>
                @if ($villa?->owner?->email || $villa?->location)<span class="subtext">{{ $villa->owner?->email }}{{ $villa->owner?->email && $villa->location ? ' · ' : '' }}{{ $villa->location }}</span>@endif
            </td>
            <td><div class="label">{{ __('Prepared by') }}</div><div class="meta-value">{{ $generatedBy->name }}</div>
                <span class="subtext">{{ __('Report reference') }}: {{ $reportRef }}</span>
            </td>
        </tr></table>
    </div>

    @if (! $hasFinal)
        <div class="empty"><h2>{{ __('Laporan belum Final') }}</h2><p>{{ __('Manager sedang memeriksa laporan periode ini. Anda akan mendapat notifikasi saat laporan sudah Final.') }}</p></div>
    @elseif ($s->isEmpty())
        <div class="empty"><h2>{{ __('No activity in this period.') }}</h2><p>{{ __('Tidak ada pemasukan.') }} {{ __('Tidak ada pengeluaran valid.') }}</p></div>
    @else
        @if ($restricted)<div class="note">{{ __('Laporan ini hanya memuat bulan yang sudah ditandai Final oleh Manager.') }}</div>@endif

        <table class="hero"><tr>
            <td><div class="hero-label">{{ __('Final net profit') }}</div><div class="hero-amount{{ $moneyClass($s->netProfit) }}">{{ Rupiah::format($s->netProfit) }}</div></td>
            <td class="hero-margin"><div class="hero-label">{{ __('Margin') }}</div><div class="value">{{ $s->marginLabel() }}</div></td>
        </tr></table>
        <table class="kpi"><tr>
            <td><div class="label">{{ __('Gross booking income') }}</div><div class="value{{ $moneyClass($s->gross) }}">{{ Rupiah::format($s->gross) }}</div></td>
            <td><div class="label">{{ __('Management fee') }}</div><div class="value{{ $moneyClass($s->managementFee) }}">{{ Rupiah::format($s->managementFee) }}</div></td>
            <td><div class="label">{{ __('Total pengeluaran') }}</div><div class="value{{ $moneyClass($s->expenses) }}">{{ Rupiah::format($s->expenses) }}</div></td>
        </tr></table>

        @if ($compactOverview)<table><tr><td class="overview-left">@endif
        <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Ringkasan perhitungan') }}</h2>
        <table class="ledger">
            <tr><td>{{ __('Gross booking income') }}</td><td class="n">{{ Rupiah::format($s->gross) }}</td></tr>
            <tr class="deduction"><td>{{ __('− Komisi platform') }}</td><td class="n">{{ Rupiah::format($s->platformFee) }}</td></tr>
            <tr class="deduction"><td>{{ __('− Potongan lain') }}</td><td class="n">{{ Rupiah::format($s->otherDeduction) }}</td></tr>
            <tr class="subtotal"><td>{{ __('Net booking revenue') }}</td><td class="n">{{ Rupiah::format($s->netRevenue) }}</td></tr>
            <tr class="deduction"><td>{{ __('− Management fee') }}</td><td class="n">{{ Rupiah::format($s->managementFee) }}</td></tr>
            <tr><td>{{ __('Revenue after fee') }}</td><td class="n">{{ Rupiah::format($s->revenueAfterFee) }}</td></tr>
            <tr class="deduction"><td>{{ __('− Total pengeluaran (Valid)') }}</td><td class="n">{{ Rupiah::format($s->expenses) }}</td></tr>
            <tr class="total"><td>{{ __('Final net profit') }}</td><td class="n">{{ Rupiah::format($s->netProfit) }}</td></tr>
        </table>
        @if ($compactOverview)</td><td class="overview-right">@endif
        <div class="note"><div class="label">{{ __('Basis management fee') }}</div>
            @foreach ($feeInfo as $vid => $label)<p>@if (! $villa)<strong>{{ $villas->firstWhere('id', $vid)?->name }}:</strong> @endif{{ $label }}</p>@endforeach
        </div>
        @if ($compactOverview)
            <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Pengeluaran per kategori') }}</h2>
            <table class="grid">
                <thead><tr><th style="width: 55%">{{ __('Kategori') }}</th><th class="n">{{ __('Nominal') }}</th></tr></thead>
                <tbody>
                @forelse ($byCategory as $category)
                    <tr><td>{{ __($category->name) }}<span class="subtext">{{ __(':count transaksi', ['count' => $category->cnt]) }}</span></td><td class="n">{{ $tableMoney($category->total) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">{{ __('Tidak ada pengeluaran valid.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </td></tr></table>
        @endif

        <div class="{{ $byPlatform->count() <= 6 ? 'keep-together' : '' }}">
        <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Pemasukan per platform') }}</h2>
        <table class="grid">
            <thead><tr><th style="width: 28%">{{ __('Platform') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Komisi') }}</th><th class="n">{{ __('Management fee') }}</th></tr></thead>
            <tbody>
            @forelse ($byPlatform as $p)
                <tr><td>{{ $p->name }}</td><td class="n">{{ $tableMoney($p->gross) }}</td><td class="n">{{ $tableMoney($p->platform_fee) }}</td><td class="n">{{ $tableMoney($p->fee_amount) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        @unless ($compactOverview)
        <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Pengeluaran per kategori') }}</h2>
        <table class="grid">
            <thead><tr><th style="width: 58%">{{ __('Kategori') }}</th><th class="n">{{ __('Jumlah transaksi') }}</th><th class="n">{{ __('Nominal') }}</th></tr></thead>
            <tbody>
            @forelse ($byCategory as $category)
                <tr><td>{{ __($category->name) }}</td><td class="n">{{ $category->cnt }}</td><td class="n">{{ $tableMoney($category->total) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('Tidak ada pengeluaran valid.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        @endunless

        @if ($series->isNotEmpty() || (! $villa && $byVilla->isNotEmpty()))
            <div class="page-break"></div>
            @if ($series->isNotEmpty())
                <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Per bulan') }}</h2>
                <table class="grid">
                    <thead><tr><th style="width: 13%">{{ __('Bulan') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Komisi+pot.') }}</th><th class="n">{{ __('Mgmt fee') }}</th><th class="n">{{ __('Pengeluaran') }}</th><th class="n">{{ __('Net profit') }}</th></tr></thead>
                    <tbody>
                    @foreach ($series as $row)
                        @php $x = $row['summary']; @endphp
                        <tr><td>{{ $row['label'] }}</td><td class="n">{{ $tableMoney($x->gross) }}</td><td class="n">{{ $tableMoney(F::sum([$x->platformFee, $x->otherDeduction])) }}</td><td class="n">{{ $tableMoney($x->managementFee) }}</td><td class="n">{{ $tableMoney($x->expenses) }}</td><td class="n">{{ $tableMoney($x->netProfit) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            @if (! $villa && $byVilla->isNotEmpty())
                <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Per villa') }}</h2>
                <table class="grid">
                    <thead><tr><th style="width: 24%">{{ __('Villa') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Mgmt fee') }}</th><th class="n">{{ __('Pengeluaran') }}</th><th class="n">{{ __('Net profit') }}</th><th class="n">{{ __('Margin') }}</th></tr></thead>
                    <tbody>
                    @foreach ($byVilla as $row)
                        @php $x = $row['summary']; @endphp
                        <tr><td>{{ $row['villa']->name }}</td><td class="n">{{ $tableMoney($x->gross) }}</td><td class="n">{{ $tableMoney($x->managementFee) }}</td><td class="n">{{ $tableMoney($x->expenses) }}</td><td class="n">{{ $tableMoney($x->netProfit) }}</td><td class="n">{{ $x->marginLabel() }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        @endif

        <div class="page-break"></div>
        <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Management fee breakdown') }}</h2>
        <p class="section-note">{{ __('Fees per income entry, using the percentage and calculation basis saved with each transaction.') }}</p>
        <table class="grid">
            <thead><tr><th style="width: 12%">{{ __('Recorded date') }}</th><th style="width: 22%">{{ __('Tamu / kode') }}</th><th style="width: 17%">{{ __('Villa') }} / {{ __('Sumber') }}</th><th class="n" style="width: 22%">{{ __('Basis fee (Rp)') }}</th><th class="n" style="width: 7%">{{ __('Fee %') }}</th><th class="n" style="width: 20%">{{ __('Management fee') }}</th></tr></thead>
            <tbody>
            @forelse ($incomes as $income)
                <tr>
                    <td>{{ $income->payment_date->format('d/m/Y') }}</td>
                    <td>{{ $income->booking?->guest_name ?: __('Income without a linked reservation') }}<span class="subtext">{{ $income->booking_ref ?: ($income->booking?->booking_ref ?: '#'.$income->id) }}</span>
                        @if ($income->booking)<span class="subtext">{{ $income->booking->check_in->format('d/m/Y') }} – {{ $income->booking->check_out->format('d/m/Y') }}</span>@endif
                    </td>
                    <td>{{ $income->villa->name }}<span class="subtext">{{ $income->platform->name }}</span></td>
                    <td class="n">{{ $tableMoney($income->fee_base_amount) }}<span class="subtext">{{ $income->fee_basis->label() }}</span></td>
                    <td class="n">{{ Rupiah::percent($income->fee_percentage) }}</td>
                    <td class="n">{{ $tableMoney($income->fee_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td colspan="5">{{ __('Total management fee') }}</td><td class="n">{{ $tableMoney($s->managementFee) }}</td></tr></tfoot>
        </table>

        <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Rincian pemasukan') }}</h2>
        <table class="grid">
            <thead><tr><th style="width: 11%">{{ __('Recorded date') }}</th><th style="width: 14%">{{ $villa ? __('Platform') : __('Villa').' / '.__('Platform') }}</th><th style="width: 21%">{{ __('Tamu / kode') }}</th><th class="n" style="width: 14%">{{ __('Gross') }}</th><th class="n" style="width: 13%">{{ __('Komisi+pot.') }}</th><th class="n" style="width: 13%">{{ __('Fee') }}</th><th class="n" style="width: 14%">{{ __('Net stlh fee') }}</th></tr></thead>
            <tbody>
            @forelse ($incomes as $income)
                <tr>
                    <td>{{ $income->payment_date->format('d/m/Y') }}</td>
                    <td>@unless($villa){{ $income->villa->name }}<br>@endunless{{ $income->platform->name }}{{ $income->is_net_of_platform ? '*' : '' }}</td>
                    <td>{{ $income->booking?->guest_name ?: __('Income without a linked reservation') }}<span class="subtext">{{ $income->booking_ref ?: ($income->booking?->booking_ref ?: '#'.$income->id) }}</span></td>
                    <td class="n">{{ $tableMoney($income->gross_amount) }}</td>
                    <td class="n">{{ $tableMoney(F::sum([$income->is_net_of_platform ? 0 : $income->platform_fee, $income->other_deduction])) }}</td>
                    <td class="n">{{ $tableMoney($income->fee_amount) }}<span class="subtext">{{ Rupiah::percent($income->fee_percentage) }}</span></td>
                    <td class="n">{{ $tableMoney(F::sum([$income->net_revenue, '-'.$income->fee_amount])) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td colspan="3">{{ __('Total') }}</td><td class="n">{{ $tableMoney($s->gross) }}</td><td class="n">{{ $tableMoney(F::sum([$s->platformFee, $s->otherDeduction])) }}</td><td class="n">{{ $tableMoney($s->managementFee) }}</td><td class="n">{{ $tableMoney($s->revenueAfterFee) }}</td></tr></tfoot>
        </table>
        <p class="footnote">{{ __('* nominal sudah net dari platform — komisi tidak dipotong dua kali.') }}</p>

        <h2 class="section-heading"><span>{{ str_pad(++$section, 2, '0', STR_PAD_LEFT) }}</span>{{ __('Rincian pengeluaran (Valid)') }}</h2>
        <table class="grid">
            <thead><tr><th style="width: 12%">{{ __('Tanggal') }}</th>@unless($villa)<th style="width: 18%">{{ __('Villa') }}</th>@endunless<th>{{ __('Keterangan / vendor') }}</th><th style="width: 17%">{{ __('Ref. nota') }}</th><th class="n" style="width: 18%">{{ __('Nominal') }}</th></tr></thead>
            <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense->expense_date->format('d/m/Y') }}</td>
                    @unless($villa)<td>{{ $expense->villa->name }}</td>@endunless
                    <td>{{ $expense->description }}<span class="subtext">{{ __($expense->category->name) }}@if ($expense->vendor) · {{ $expense->vendor }}@endif</span></td>
                    <td>{{ __('NOTA-:str_pad_left', ['str_pad_left' => str_pad($expense->id, 5, '0', STR_PAD_LEFT)]) }}<span class="subtext">{{ __(':count foto', ['count' => $expense->receipts->count()]) }}</span></td>
                    <td class="n">{{ $tableMoney($expense->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $villa ? 4 : 5 }}" class="muted">{{ __('Tidak ada pengeluaran valid.') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td colspan="{{ $villa ? 3 : 4 }}">{{ __('Total pengeluaran') }}</td><td class="n">{{ $tableMoney($s->expenses) }}</td></tr></tfoot>
        </table>
        <p class="footnote">{{ __('Foto nota dapat dilihat di aplikasi PropertyMe (menu Pengeluaran & Nota) dengan nomor referensi di atas.') }}</p>
        @if ($incomes->contains(fn ($income) => $income->isFromHostex()))<div class="note">{{ __('Includes automatic Hostex booking revenue; payouts are not verified.') }}</div>@endif
    @endif
    <p class="footnote">{{ __('Income and expenses are shown in Indonesian Rupiah (IDR).') }} {{ __('Pengeluaran hanya yang berstatus Valid.') }}</p>
</main>
</body>
</html>
