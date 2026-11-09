@use('App\Services\Finance\FinanceCalculator', 'F')
@use('App\Support\Rupiah')
<x-layouts.app :title="auth()->user()->isManager() ? __('Grafik & Analisis') : __('Grafik')">
    @php
        $growth = function ($cur, $prev) {
            $g = F::growth($cur, $prev);
            if ($g === null) return '<span class="fs-xs text-muted">—</span>';
            $cls = $g >= 0 ? 'up' : 'down';
            return '<span class="pm-growth '.$cls.'"><i class="bi bi-arrow-'.($g >= 0 ? 'up' : 'down').'-short" aria-hidden="true"></i>'.number_format(abs($g), 1, ',', '.').'%</span>';
        };
        $metrics = [[__('Gross'), 'gross'], [__('Komisi + potongan'), null], [__('Management fee'), 'managementFee'], [__('Pengeluaran'), 'expenses'], [__('Net profit'), 'netProfit']];
        $val = fn ($s, $k) => $k ? $s->{$k} : F::sum([$s->platformFee, $s->otherDeduction]);
    @endphp

    <x-ui.page-header :title="auth()->user()->isManager() ? __('Grafik & Analisis') : __('Grafik')" :subtitle="__('Periode :period', ['period' => $periodLabel])" />

    {{-- Filter periode --}}
    <form method="GET" class="pm-card pm-toolbar mb-3" data-no-lock x-data="{ preset: @js($params['preset']) }">
        <select name="preset" class="form-select" aria-label="{{ __('Periode') }}" x-model="preset" @change="preset !== 'custom' && $el.form.submit()">
            @foreach ($presets as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
        </select>
        <template x-if="preset === 'custom'">
            <span class="d-flex gap-2 flex-wrap">
                <input type="date" name="from" value="{{ $params['from'] }}" class="form-control" aria-label="{{ __('Dari') }}" required>
                <input type="date" name="to" value="{{ $params['to'] }}" class="form-control" aria-label="{{ __('Sampai') }}" required>
            </span>
        </template>
        <select name="granularity" class="form-select" aria-label="{{ __('Tampilan') }}" onchange="this.form.submit()">
            <option value="month" @selected($params['granularity'] === 'month')>{{ __('Bulanan') }}</option>
            <option value="quarter" @selected($params['granularity'] === 'quarter')>{{ __('Kuartalan') }}</option>
            <option value="year" @selected($params['granularity'] === 'year')>{{ __('Tahunan') }}</option>
        </select>
        <select name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()" style="flex: 1 1 200px">
            <option value="">{{ __('Semua villa') }}</option>
            @foreach ($villas as $id => $n)<option value="{{ $id }}" @selected($params['villa'] === $id)>{{ $n }}</option>@endforeach
        </select>
        <input type="hidden" name="trend" value="{{ $params['trend'] }}">
        <button type="submit" class="btn btn-light"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
    </form>
    @error('from')<div class="alert alert-danger">{{ $message }}</div>@enderror

    @if ($restricted)
        <div class="alert alert-info d-flex gap-2"><i class="bi bi-info-circle" aria-hidden="true"></i><div class="fs-sm">{{ __('Grafik hanya memuat bulan yang laporannya sudah Final.') }}</div></div>
    @endif

    {{-- Perbandingan --}}
    <x-ui.card :title="__('Perbandingan periode')" :subtitle="__('Periode terpilih vs periode sebelumnya (sama panjang) & tahun lalu')" flush class="mb-3">
        <div class="table-responsive">
            <table class="table pm-table pm-table--stack">
                <thead><tr><th>{{ __('Metrik') }}</th><th class="num">{{ __('Periode ini') }}</th><th class="num">{{ __('Sebelumnya') }}<div class="fw-normal text-lowercase">{{ $prevLabel }}</div></th><th class="num">{{ __('Pertumbuhan') }}</th><th class="num">{{ __('Tahun lalu') }}<div class="fw-normal text-lowercase">{{ $yoyLabel }}</div></th><th class="num">{{ __('vs tahun lalu') }}</th></tr></thead>
                <tbody>
                @foreach ($metrics as [$label, $key])
                    <tr @class(['fw-semibold' => $key === 'netProfit'])>
                        <td class="stack-full cell-title">{{ $label }}</td>
                        <td data-label="{{ __('Periode ini') }}" class="num {{ $key === 'netProfit' && $current->isLoss() ? 'text-negative' : 'text-ink' }}">@rupiah($val($current, $key))</td>
                        <td data-label="{{ __('Sebelumnya') }}" class="num">@rupiah($val($previous, $key))</td>
                        <td data-label="{{ __('Pertumbuhan') }}" class="num">{!! $growth($val($current, $key), $val($previous, $key)) !!}</td>
                        <td data-label="{{ __('Tahun lalu') }}" class="num">@rupiah($val($yoy, $key))</td>
                        <td data-label="{{ __('vs tahun lalu') }}" class="num">{!! $growth($val($current, $key), $val($yoy, $key)) !!}</td>
                    </tr>
                @endforeach
                <tr><td class="stack-full cell-title">{{ __('Margin') }}</td><td data-label="{{ __('Periode ini') }}" class="num">{{ $current->marginLabel() }}</td><td data-label="{{ __('Sebelumnya') }}" class="num">{{ $previous->marginLabel() }}</td><td></td><td data-label="{{ __('Tahun lalu') }}" class="num">{{ $yoy->marginLabel() }}</td><td></td></tr>
                </tbody>
            </table>
        </div>
    </x-ui.card>

    {{-- (A) Pemasukan vs fee vs pengeluaran vs laba --}}
    <x-ui.card :title="__('Pemasukan vs management fee vs pengeluaran vs laba')" :subtitle="['month' => __('Per bulan'), 'quarter' => __('Per kuartal'), 'year' => __('Per tahun')][$params['granularity']]" class="mb-3">
        <div x-data="chart({ type: 'bar', height: 320, labels: @js($rows->pluck('label')->values()), series: [
                { name: @js(__('Gross income')), data: @js($rows->map(fn ($r) => F::toFloat($r['summary']->gross))->values()) },
                { name: @js(__('Management fee')), data: @js($rows->map(fn ($r) => F::toFloat($r['summary']->managementFee))->values()) },
                { name: @js(__('Pengeluaran')), data: @js($rows->map(fn ($r) => F::toFloat($r['summary']->expenses))->values()) },
                { name: @js(__('Net profit')), data: @js($rows->map(fn ($r) => F::toFloat($r['summary']->netProfit))->values()) } ],
                colors: ['#d5b363', '#b88b2b', '#eadfc9', '#a77c25'] })">
            <div x-show="loading"><x-ui.skeleton height="300px" /></div>
            <x-ui.empty-state x-show="empty" x-cloak :title="__('Belum ada data di periode ini')" />
            <div x-ref="chart"></div>
        </div>
        <div class="table-responsive mt-3">
            <table class="table pm-table pm-table--stack fs-sm mb-0">
                <thead><tr><th>{{ __('Periode') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Komisi') }}</th><th class="num">{{ __('Management fee') }}</th><th class="num">{{ __('Pengeluaran') }}</th><th class="num">{{ __('Net profit') }}</th><th class="num">{{ __('Pertumbuhan laba') }}</th></tr></thead>
                <tbody>
                @php $prevRow = null; @endphp
                @foreach ($rows as $r)
                    @php $x = $r['summary']; @endphp
                    <tr>
                        <td class="stack-full cell-title">{{ $r['label'] }}</td>
                        <td data-label="{{ __('Gross') }}" class="num">@rupiah($x->gross)</td>
                        <td data-label="{{ __('Komisi') }}" class="num">@rupiah(F::sum([$x->platformFee, $x->otherDeduction]))</td>
                        <td data-label="{{ __('Management fee') }}" class="num">@rupiah($x->managementFee)</td>
                        <td data-label="{{ __('Pengeluaran') }}" class="num">@rupiah($x->expenses)</td>
                        <td data-label="{{ __('Net profit') }}" class="num fw-semibold {{ $x->isLoss() ? 'text-negative' : 'text-ink' }}">@rupiah($x->netProfit)</td>
                        <td data-label="{{ __('Pertumbuhan') }}" class="num">{!! $prevRow ? $growth($x->netProfit, $prevRow->netProfit) : '<span class="fs-xs text-muted">—</span>' !!}</td>
                    </tr>
                    @php $prevRow = $x; @endphp
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div class="row g-3 mb-3">
        {{-- (B) Tren laba --}}
        <div class="col-xl-8">
            <x-ui.card :title="__('Tren laba bersih')" :subtitle="__(':count bulan terakhir', ['count' => $params['trend']])">
                <x-slot:actions>
                    <div class="btn-group" role="group" aria-label="{{ __('Rentang tren') }}">
                        @foreach ([6, 12, 24] as $n)
                            <a href="{{ request()->fullUrlWithQuery(['trend' => $n]) }}" class="btn btn-sm {{ $params['trend'] === $n ? 'btn-primary' : 'btn-light' }}">{{ __(':n bln', ['n' => $n]) }}</a>
                        @endforeach
                    </div>
                </x-slot:actions>
                <div x-data="chart({ type: 'line', height: 280, labels: @js($trendSeries->pluck('label')->values()), series: [
                        { name: @js(__('Net profit')), data: @js($trendSeries->map(fn ($r) => F::toFloat($r['summary']->netProfit))->values()) },
                        { name: @js(__('Gross income')), data: @js($trendSeries->map(fn ($r) => F::toFloat($r['summary']->gross))->values()) } ],
                        colors: ['#a77c25', '#d5b363'] })">
                    <div x-show="loading"><x-ui.skeleton height="260px" /></div>
                    <x-ui.empty-state x-show="empty" x-cloak :title="__('Belum ada data')" />
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
        {{-- (C) Platform --}}
        <div class="col-xl-4">
            <x-ui.card :title="__('Pemasukan per platform')">
                <div x-data="chart({ type: 'donut', height: 280, labels: @js($byPlatform->pluck('name')), series: @js($byPlatform->map(fn ($p) => F::toFloat($p->gross))), colors: @js($byPlatform->pluck('color')) })">
                    <div x-show="loading"><x-ui.skeleton height="260px" /></div>
                    <p x-show="empty" x-cloak class="text-muted text-center py-5 mb-0">{{ __('Belum ada pemasukan.') }}</p>
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="row g-3">
        {{-- (D) Kategori --}}
        <div class="col-xl-4">
            <x-ui.card :title="__('Rincian pengeluaran per kategori')">
                @php $cats = $byCategory->take(5); $rest = $byCategory->slice(5); @endphp
                <div x-data="chart({ type: 'donut', height: 280,
                        labels: @js($cats->pluck('name')->map(fn ($n) => __($n))->when($rest->isNotEmpty(), fn ($c) => $c->push(__('Lainnya')))->values()),
                        series: @js($cats->map(fn ($c) => F::toFloat($c->total))->when($rest->isNotEmpty(), fn ($c) => $c->push(F::toFloat(F::sum($rest->pluck('total')))))->values()) })">
                    <div x-show="loading"><x-ui.skeleton height="260px" /></div>
                    <p x-show="empty" x-cloak class="text-muted text-center py-5 mb-0">{{ __('Belum ada pengeluaran valid.') }}</p>
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
        {{-- (E) Laba antar villa --}}
        <div class="col-xl-4">
            <x-ui.card :title="__('Perbandingan laba antar villa')">
                <div x-data="chart({ type: 'bar', horizontal: true, height: {{ max(220, $byVilla->count() * 44 + 60) }}, labels: @js($byVilla->map(fn ($r) => $r['villa']->name)->values()),
                        series: [{ name: @js(__('Net profit')), data: @js($byVilla->map(fn ($r) => F::toFloat($r['summary']->netProfit))->values()) }], colors: ['#157F3D'] })">
                    <div x-show="loading"><x-ui.skeleton height="220px" /></div>
                    <p x-show="empty" x-cloak class="text-muted text-center py-5 mb-0">{{ __('Belum ada data.') }}</p>
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
        {{-- (F) Fee per villa --}}
        <div class="col-xl-4">
            <x-ui.card :title="__('Management fee per villa')">
                <div x-data="chart({ type: 'bar', horizontal: true, height: {{ max(220, $byVilla->count() * 44 + 60) }}, labels: @js($byVilla->map(fn ($r) => $r['villa']->name)->values()),
                        series: [{ name: @js(__('Management fee')), data: @js($byVilla->map(fn ($r) => F::toFloat($r['summary']->managementFee))->values()) }], colors: ['#F0A30A'] })">
                    <div x-show="loading"><x-ui.skeleton height="220px" /></div>
                    <p x-show="empty" x-cloak class="text-muted text-center py-5 mb-0">{{ __('Belum ada data.') }}</p>
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
