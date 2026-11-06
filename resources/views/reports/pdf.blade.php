@php
    use App\Support\Rupiah;
    use App\Services\Finance\FinanceCalculator as F;
    $s = $summary;
    $maxSeries = $series->isNotEmpty() ? max(1, $series->max(fn ($r) => max(F::toFloat($r['summary']->gross), abs(F::toFloat($r['summary']->netProfit))))) : 1;
    $catTotal = max(1, F::toFloat($s->expenses));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ __('Laporan :periodlabel', ['periodlabel' => $periodLabel]) }}</title>
<style>
    @page { margin: 96px 40px 64px 40px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 9.5pt; color: #46574f; line-height: 1.45; }
    header { position: fixed; top: -76px; left: 0; right: 0; height: 60px; border-bottom: 2px solid #203c34; }
    footer { position: fixed; bottom: -44px; left: 0; right: 0; height: 24px; font-size: 7.5pt; color: #6c766f; border-top: 1px solid #e7e9e2; padding-top: 6px; }
    footer .pg:after { content: "Halaman " counter(page) " dari " counter(pages); }
    .mark { display: inline-block; width: 34px; height: 34px; background: #285b49; color: #fff; font-weight: bold; text-align: center; line-height: 34px; border-radius: 8px; font-size: 12pt; }
    h1 { font-size: 15pt; color: #203c34; margin: 0 0 2px; }
    h2 { font-size: 11pt; color: #203c34; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e7e9e2; }
    .muted { color: #6c766f; }
    .small { font-size: 8pt; }
    table { width: 100%; border-collapse: collapse; }
    .info td { padding: 2px 0; vertical-align: top; }
    .kpi td { width: 25%; padding: 10px; border: 1px solid #e7e9e2; vertical-align: top; }
    .kpi .lbl { font-size: 7.5pt; color: #6c766f; text-transform: uppercase; letter-spacing: .4px; }
    .kpi .val { font-size: 12.5pt; font-weight: bold; color: #203c34; margin-top: 3px; }
    .kpi td.hero { background: #244d3d; border-color: #244d3d; }
    .kpi td.hero .lbl { color: #d1ddc8; } .kpi td.hero .val { color: #fff; font-size: 14pt; }
    .ledger td { padding: 5px 0; border-bottom: 1px solid #eff2eb; }
    .ledger td.r { text-align: right; font-weight: bold; color: #203c34; white-space: nowrap; }
    .ledger tr.sub td { color: #6c766f; padding-left: 14px; } .ledger tr.sub td.r { font-weight: normal; color: #46574f; }
    .ledger tr.total td { border-top: 2px solid #203c34; border-bottom: 0; font-size: 11.5pt; font-weight: bold; padding-top: 8px; }
    .grid th { background: #f7f8f4; color: #6c766f; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .3px; text-align: left; padding: 6px; border-bottom: 1px solid #d4dbd2; }
    .grid td { padding: 5px 6px; border-bottom: 1px solid #eff2eb; vertical-align: top; }
    .grid tr:nth-child(even) td { background: #f8faf5; }
    .grid tfoot td { font-weight: bold; color: #203c34; border-top: 1px solid #203c34; background: #fff !important; }
    .n { text-align: right; white-space: nowrap; }
    .neg { color: #b42318; } .pos { color: #157f3d; }
    .bar { height: 8px; background: #285b49; border-radius: 2px; }
    .bar.neg-b { background: #b42318; } .bar.exp { background: #f0a30a; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 7.5pt; font-weight: bold; }
    .final { background: #eaf1e8; color: #285b49; } .draft { background: #eef1eb; color: #59655d; }
    .page-break { page-break-before: always; }
    .two td { width: 50%; vertical-align: top; }
</style>
</head>
<body>
<header>
    <table><tr>
        <td style="width: 44px"><span class="mark">PM</span></td>
        <td><div style="font-size: 12pt; font-weight: bold; color: #203c34">PropertyMe</div><div class="small muted">{{ __('Villa Financial Management · Bali') }}</div></td>
        <td style="text-align: right" class="small muted">
            {{ __('Laporan :bulanan', ['bulanan' => $type === 'annual' ? __('Tahunan') : __('Bulanan')]) }}<br>
            <strong style="color:#203c34; font-size: 10pt">{{ $periodLabel }}</strong>
        </td>
    </tr></table>
</header>
<footer>
    <table><tr>
        <td>{{ __('Dibuat :i · Semua angka dihitung otomatis dari transaksi tercatat. Pengeluaran hanya yang berstatus Valid.', ['i' => $generatedAt->translatedFormat('d M Y H:i')]) }}</td>
        <td style="text-align: right" class="pg"></td>
    </tr></table>
</footer>

<main>
    <h1>{{ __('Laporan Keuangan :villa', ['villa' => $villa?->name ?? __('Semua Villa')]) }}</h1>
    <table class="info" style="margin-bottom: 12px">
        <tr><td style="width: 120px" class="muted">{{ __('Periode') }}</td><td>{{ $from->translatedFormat('d F Y') }} – {{ $to->translatedFormat('d F Y') }}</td>
            <td style="text-align:right">
                @if ($type === 'monthly' && $villa)
                    <span class="badge {{ $monthStatus?->status === \App\Enums\ReportStatus::Final ? 'final' : 'draft' }}">{{ $monthStatus?->status === \App\Enums\ReportStatus::Final ? 'FINAL' : 'DRAFT' }}</span>
                @endif
            </td></tr>
        @if ($villa)
            <tr><td class="muted">{{ __('Lokasi') }}</td><td colspan="2">{{ $villa->location ?: '—' }}</td></tr>
            <tr><td class="muted">Owner</td><td colspan="2">{{ $villa->owner?->name ?? '—' }}{{ $villa->owner?->email ? ' · '.$villa->owner->email : '' }}</td></tr>
        @else
            <tr><td class="muted">{{ __('Villa') }}</td><td colspan="2">{{ $villas->pluck('name')->join(', ') }}</td></tr>
        @endif
        <tr><td class="muted">{{ __('Management fee') }}</td><td colspan="2">
            @foreach ($feeInfo as $vid => $label){{ ! $villa ? $villas->firstWhere('id', $vid)?->name.': ' : '' }}{{ $label }}@if(! $loop->last)<br>@endif @endforeach
        </td></tr>
    </table>

    @if ($restricted)
        <p class="small muted">{{ __('Laporan ini hanya memuat bulan yang sudah ditandai Final oleh Manager.') }}</p>
    @endif

    <table class="kpi"><tr>
        <td><div class="lbl">{{ __('Gross booking income') }}</div><div class="val">{{ Rupiah::format($s->gross) }}</div></td>
        <td><div class="lbl">{{ __('Management fee') }}</div><div class="val">{{ Rupiah::format($s->managementFee) }}</div></td>
        <td><div class="lbl">{{ __('Total pengeluaran') }}</div><div class="val">{{ Rupiah::format($s->expenses) }}</div></td>
        <td class="hero"><div class="lbl">{{ __('Final net profit') }}</div><div class="val">{{ Rupiah::format($s->netProfit) }}</div><div class="lbl" style="margin-top:3px">{{ __('Margin :marginlabel', ['marginlabel' => $s->marginLabel()]) }}</div></td>
    </tr></table>

    <table class="two" style="margin-top: 6px"><tr>
        <td style="padding-right: 14px">
            <h2>{{ __('Ringkasan perhitungan') }}</h2>
            <table class="ledger">
                <tr><td>{{ __('Gross booking income') }}</td><td class="r">{{ Rupiah::format($s->gross) }}</td></tr>
                <tr class="sub"><td>{{ __('− Komisi platform') }}</td><td class="r">{{ Rupiah::format($s->platformFee) }}</td></tr>
                <tr class="sub"><td>{{ __('− Potongan lain') }}</td><td class="r">{{ Rupiah::format($s->otherDeduction) }}</td></tr>
                <tr><td><strong>{{ __('Net booking revenue') }}</strong></td><td class="r">{{ Rupiah::format($s->netRevenue) }}</td></tr>
                <tr class="sub"><td>{{ __('− Management fee') }}</td><td class="r">{{ Rupiah::format($s->managementFee) }}</td></tr>
                <tr><td><strong>{{ __('Revenue after fee') }}</strong></td><td class="r">{{ Rupiah::format($s->revenueAfterFee) }}</td></tr>
                <tr class="sub"><td>{{ __('− Total pengeluaran (Valid)') }}</td><td class="r">{{ Rupiah::format($s->expenses) }}</td></tr>
                <tr class="total"><td>{{ __('Final net profit') }}</td><td class="r {{ $s->isLoss() ? 'neg' : 'pos' }}">{{ Rupiah::format($s->netProfit) }}</td></tr>
                <tr class="sub"><td>{{ __('Net profit margin') }}</td><td class="r">{{ $s->marginLabel() }}</td></tr>
            </table>
        </td>
        <td style="padding-left: 14px">
            <h2>{{ __('Pemasukan per platform') }}</h2>
            <table class="grid">
                <thead><tr><th>{{ __('Platform') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Komisi') }}</th><th class="n">{{ __('Fee') }}</th></tr></thead>
                @forelse ($byPlatform as $p)
                    <tr><td>{{ $p->name }}</td><td class="n">{{ Rupiah::format($p->gross) }}</td><td class="n">{{ Rupiah::format($p->platform_fee) }}</td><td class="n">{{ Rupiah::format($p->fee_amount) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
                @endforelse
            </table>

            <h2>{{ __('Pengeluaran per kategori') }}</h2>
            <table class="grid">
                @forelse ($byCategory as $c)
                    <tr>
                        <td style="width: 42%">{{ __($c->name) }}</td>
                        <td style="width: 30%"><div class="bar exp" style="width: {{ max(2, round(F::toFloat($c->total) / $catTotal * 100)) }}%"></div></td>
                        <td class="n">{{ Rupiah::format($c->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="muted">{{ __('Tidak ada pengeluaran valid.') }}</td></tr>
                @endforelse
            </table>
        </td>
    </tr></table>

    @if ($series->isNotEmpty())
        <h2>{{ __('Per bulan') }}</h2>
        <table class="grid">
            <thead><tr><th>{{ __('Bulan') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Komisi+pot.') }}</th><th class="n">{{ __('Mgmt fee') }}</th><th class="n">{{ __('Pengeluaran') }}</th><th class="n">{{ __('Net profit') }}</th><th style="width: 18%">{{ __('Grafik laba') }}</th></tr></thead>
            @foreach ($series as $row)
                @php $x = $row['summary']; $np = F::toFloat($x->netProfit); @endphp
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="n">{{ Rupiah::format($x->gross) }}</td>
                    <td class="n">{{ Rupiah::format(F::sum([$x->platformFee, $x->otherDeduction])) }}</td>
                    <td class="n">{{ Rupiah::format($x->managementFee) }}</td>
                    <td class="n">{{ Rupiah::format($x->expenses) }}</td>
                    <td class="n {{ $np < 0 ? 'neg' : '' }}">{{ Rupiah::format($x->netProfit) }}</td>
                    <td><div class="bar {{ $np < 0 ? 'neg-b' : '' }}" style="width: {{ max(1, round(abs($np) / $maxSeries * 100)) }}%"></div></td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! $villa && $byVilla->isNotEmpty())
        <h2>{{ __('Per villa') }}</h2>
        <table class="grid">
            <thead><tr><th>{{ __('Villa') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Mgmt fee') }}</th><th class="n">{{ __('Pengeluaran') }}</th><th class="n">{{ __('Net profit') }}</th><th class="n">{{ __('Margin') }}</th></tr></thead>
            @foreach ($byVilla as $row)
                @php $x = $row['summary']; @endphp
                <tr><td>{{ $row['villa']->name }}</td><td class="n">{{ Rupiah::format($x->gross) }}</td><td class="n">{{ Rupiah::format($x->managementFee) }}</td><td class="n">{{ Rupiah::format($x->expenses) }}</td><td class="n {{ $x->isLoss() ? 'neg' : '' }}">{{ Rupiah::format($x->netProfit) }}</td><td class="n">{{ $x->marginLabel() }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="page-break"></div>
    <h2>{{ __('Rincian pemasukan') }}</h2>
    <table class="grid">
        <thead><tr><th>{{ __('Tgl bayar') }}</th>@unless($villa)<th>{{ __('Villa') }}</th>@endunless<th>{{ __('Platform') }}</th><th>{{ __('Tamu / kode') }}</th><th class="n">{{ __('Gross') }}</th><th class="n">{{ __('Komisi+pot.') }}</th><th class="n">{{ __('Fee') }}</th><th class="n">{{ __('Net stlh fee') }}</th></tr></thead>
        @forelse ($incomes as $i)
            <tr>
                <td>{{ $i->payment_date->format('d/m/Y') }}</td>
                @unless($villa)<td>{{ $i->villa->name }}</td>@endunless
                <td>{{ $i->platform->name }}{{ $i->is_net_of_platform ? '*' : '' }}</td>
                <td>{{ $i->booking?->guest_name ?: '—' }}<br><span class="small muted">{{ $i->booking_ref }}</span></td>
                <td class="n">{{ Rupiah::format($i->gross_amount) }}</td>
                <td class="n">{{ Rupiah::format(F::sum([$i->is_net_of_platform ? 0 : $i->platform_fee, $i->other_deduction])) }}</td>
                <td class="n">{{ Rupiah::format($i->fee_amount) }}<br><span class="small muted">{{ Rupiah::percent($i->fee_percentage) }}</span></td>
                <td class="n">{{ Rupiah::format(F::sum([$i->net_revenue, '-'.$i->fee_amount])) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
        @endforelse
        <tfoot><tr><td colspan="{{ $villa ? 3 : 4 }}">{{ __('Total') }}</td><td class="n">{{ Rupiah::format($s->gross) }}</td><td class="n">{{ Rupiah::format(F::sum([$s->platformFee, $s->otherDeduction])) }}</td><td class="n">{{ Rupiah::format($s->managementFee) }}</td><td class="n">{{ Rupiah::format($s->revenueAfterFee) }}</td></tr></tfoot>
    </table>
    <p class="small muted">{{ __('* nominal sudah net dari platform — komisi tidak dipotong dua kali.') }}</p>

    <h2>{{ __('Rincian pengeluaran (Valid)') }}</h2>
    <table class="grid">
        <thead><tr><th>{{ __('Tanggal') }}</th>@unless($villa)<th>{{ __('Villa') }}</th>@endunless<th>{{ __('Kategori') }}</th><th>{{ __('Keterangan / vendor') }}</th><th>{{ __('Ref. nota') }}</th><th class="n">{{ __('Nominal') }}</th></tr></thead>
        @forelse ($expenses as $e)
            <tr>
                <td>{{ $e->expense_date->format('d/m/Y') }}</td>
                @unless($villa)<td>{{ $e->villa->name }}</td>@endunless
                <td>{{ __($e->category->name) }}</td>
                <td>{{ $e->description }}@if($e->vendor)<br><span class="small muted">{{ $e->vendor }}</span>@endif</td>
                <td>{{ __('NOTA-:str_pad_left', ['str_pad_left' => str_pad($e->id, 5, '0', STR_PAD_LEFT)]) }}<br><span class="small muted">{{ __(':count foto', ['count' => $e->receipts->count()]) }}</span></td>
                <td class="n">{{ Rupiah::format($e->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ __('Tidak ada pengeluaran valid.') }}</td></tr>
        @endforelse
        <tfoot><tr><td colspan="{{ $villa ? 4 : 5 }}">{{ __('Total pengeluaran') }}</td><td class="n">{{ Rupiah::format($s->expenses) }}</td></tr></tfoot>
    </table>
    <p class="small muted">{{ __('Foto nota dapat dilihat di aplikasi PropertyMe (menu Pengeluaran & Nota) dengan nomor referensi di atas.') }}</p>
</main>
</body>
</html>
