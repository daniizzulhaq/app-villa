@use('App\Services\Finance\FinanceCalculator', 'F')
@use('App\Support\Rupiah')
<x-layouts.app :title="__('Dashboard')">
    @php
        $s = $summary;
        $g = fn ($cur, $prev) => F::growth($cur, $prev);
        $isManager = $user->isManager();
    @endphp

    <x-ui.page-header :title="__('Halo, :name', ['name' => strtok($user->name, ' ')])" :subtitle="__('Ringkasan keuangan :period', ['period' => $periodLabel]).' · '.($filters['villa'] ? $villas[$filters['villa']] : __('semua villa'))">
        <x-slot:actions>
            @if ($isManager)
                <a href="{{ route('incomes.create') }}" class="btn btn-light"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Pemasukan') }}</a>
                <a href="{{ route('bookings.create') }}" class="btn btn-light d-none d-xl-inline-flex"><i class="bi bi-calendar-plus" aria-hidden="true"></i> {{ __('Tambah Reservasi') }}</a>
            @endif
            <a href="{{ route('reports.monthly', array_filter(['villa' => $filters['villa'], 'year' => $filters['year'], 'month' => $filters['month'] ?: null])) }}" class="btn btn-primary"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ __('Lihat Laporan') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filter: semua angka & grafik ikut berubah --}}
    <form method="GET" class="pm-card pm-toolbar mb-3" data-no-lock aria-label="{{ __('Filter dashboard') }}">
        <select name="month" class="form-select" aria-label="{{ __('Bulan') }}" onchange="this.form.submit()">
            <option value="0" @selected($filters['month'] === 0)>{{ __('Setahun penuh') }}</option>
            @for ($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($filters['month'] === $m)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>@endfor
        </select>
        <select name="year" class="form-select" aria-label="{{ __('Tahun') }}" onchange="this.form.submit()" style="flex: 0 1 110px">
            @for ($y = today()->year + 1; $y >= today()->year - 5; $y--)<option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>@endfor
        </select>
        <select name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()" style="flex: 1 1 200px">
            <option value="">{{ __('Semua villa') }}</option>
            @foreach ($villas as $id => $n)<option value="{{ $id }}" @selected($filters['villa'] === $id)>{{ $n }}</option>@endforeach
        </select>
        <select name="platform" class="form-select" aria-label="{{ __('Sumber booking') }}" onchange="this.form.submit()">
            <option value="">{{ __('Semua sumber') }}</option>
            @foreach ($platforms as $id => $n)<option value="{{ $id }}" @selected($filters['platform'] === $id)>{{ $n }}</option>@endforeach
        </select>
        @if ($filters['villa'] || $filters['platform'] || $filters['month'] !== today()->month || $filters['year'] !== today()->year)
            <a href="{{ route('dashboard') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
        @endif
    </form>
    @if ($filters['platform'])
        <p class="fs-sm text-muted mt-n2 mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ __('Filter sumber hanya berlaku untuk pemasukan; pengeluaran tetap dihitung seluruhnya.') }}</p>
    @endif

    {{-- KPI: Net Profit paling menonjol --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-ui.stat hero :label="__('Total net profit')" :value="Rupiah::format($s->netProfit)" :tone="$s->isLoss() ? 'negative' : 'positive'" icon="bi-trophy"
                       :hint="($p = $g($s->netProfit, $previous->netProfit)) !== null ? ($p >= 0 ? '▲ ' : '▼ ').number_format(abs($p), 1, ',', '.').'% dari periode sebelumnya' : __('Revenue after fee − pengeluaran valid')" />
        </div>
        <div class="col-lg-8">
            <div class="row g-3 h-100">
                <div class="col-6 col-md-4"><x-ui.stat :label="__('Total gross income')" :value="Rupiah::short($s->gross)" :hint="Rupiah::format($s->gross)" icon="bi-cash-stack" /></div>
                <div class="col-6 col-md-4"><x-ui.stat :label="__('Total management fee')" :value="Rupiah::short($s->managementFee)" :hint="Rupiah::format($s->managementFee)" icon="bi-percent" icon-tone="info" /></div>
                <div class="col-6 col-md-4"><x-ui.stat :label="__('Total pengeluaran')" :value="Rupiah::short($s->expenses)" :hint="__(':count nota valid', ['count' => $s->expenseCount])" icon="bi-receipt" icon-tone="warning" /></div>
                <div class="col-6 col-md-4"><x-ui.stat :label="__('Net profit margin')" :value="$s->marginLabel()" :tone="$s->margin() === null ? null : ($s->isLoss() ? 'negative' : 'positive')" :hint="__('Net profit ÷ gross')" icon="bi-pie-chart" icon-tone="success" /></div>
                <div class="col-6 col-md-4"><x-ui.stat :label="__('Villa aktif')" :value="$activeVillas" icon="bi-houses" /></div>
                <div class="col-6 col-md-4"><x-ui.stat :label="__('Net booking revenue')" :value="Rupiah::short($s->netRevenue)" :hint="__('Setelah komisi & potongan')" icon="bi-wallet2" /></div>
            </div>
        </div>
    </div>

    @if ($isManager)
        {{-- Widget kerja Manager --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <x-ui.card :title="__('Nota menunggu pemeriksaan')" flush>
                    <x-slot:actions><x-ui.badge :variant="$widgets['pendingCount'] ? 'warning' : 'success'">{{ $widgets['pendingCount'] }}</x-ui.badge></x-slot:actions>
                    @forelse ($widgets['pendingList'] as $e)
                        <a href="{{ route('reviews.show', $e) }}" class="d-flex justify-content-between gap-2 px-4 py-2 border-bottom text-decoration-none">
                            <span class="min-w-0"><span class="d-block text-ink fw-semibold text-truncate fs-sm">{{ $e->description }}</span><span class="d-block fs-xs text-muted">{{ $e->villa->name }} · {{ $e->creator?->name }}</span></span>
                            <span class="num fs-sm fw-semibold text-ink">{{ Rupiah::short($e->amount) }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-3 text-muted fs-sm mb-0"><i class="bi bi-check2-circle text-positive" aria-hidden="true"></i> {{ __('Semua nota sudah diperiksa.') }}</p>
                    @endforelse
                    @if ($widgets['pendingCount'])<div class="p-3"><a href="{{ route('reviews.index') }}" class="btn btn-sm btn-primary w-100">{{ __('Periksa nota') }}</a></div>@endif
                </x-ui.card>
            </div>
            <div class="col-md-4">
                <x-ui.card :title="__('Booking perlu dilengkapi')" flush>
                    <x-slot:actions><x-ui.badge :variant="$widgets['needsCount'] ? 'warning' : 'success'">{{ $widgets['needsCount'] }}</x-ui.badge></x-slot:actions>
                    @forelse ($widgets['needsList'] as $b)
                        <a href="{{ route('bookings.edit', $b) }}" class="d-flex gap-2 align-items-center px-4 py-2 border-bottom text-decoration-none">
                            <span class="pm-platform-dot" style="background: {{ $b->platform->color }}" aria-hidden="true"></span>
                            <span class="min-w-0"><span class="d-block text-ink fw-semibold fs-sm text-truncate">{{ $b->villa->name }}</span><span class="d-block fs-xs text-muted">{{ $b->platform->name }} · {{ $b->check_in->translatedFormat('d M') }}–{{ $b->check_out->translatedFormat('d M') }}</span></span>
                        </a>
                    @empty
                        <p class="px-4 py-3 text-muted fs-sm mb-0"><i class="bi bi-check2-circle text-positive" aria-hidden="true"></i> {{ __('Semua data booking lengkap.') }}</p>
                    @endforelse
                    @if ($widgets['needsCount'])<div class="p-3"><a href="{{ route('bookings.index', ['status' => 'needs_completion']) }}" class="btn btn-sm btn-light w-100">{{ __('Lihat semua') }}</a></div>@endif
                </x-ui.card>
            </div>
            <div class="col-md-4">
                @include('dashboard._today')
            </div>
        </div>
    @endif

    {{-- Grafik --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <x-ui.card :title="__('Pemasukan vs management fee vs pengeluaran vs laba')" :subtitle="$filters['month'] ? __('6 bulan terakhir hingga :period', ['period' => $periodLabel]) : __('Per bulan, :period', ['period' => $periodLabel])">
                <div x-data="chart({ type: 'bar', height: 320, labels: @js($series->pluck('label')->values()), series: [
                        { name: @js(__('Gross income')), data: @js($series->map(fn ($r) => F::toFloat($r['summary']->gross))->values()) },
                        { name: @js(__('Management fee')), data: @js($series->map(fn ($r) => F::toFloat($r['summary']->managementFee))->values()) },
                        { name: @js(__('Pengeluaran')), data: @js($series->map(fn ($r) => F::toFloat($r['summary']->expenses))->values()) },
                        { name: @js(__('Net profit')), data: @js($series->map(fn ($r) => F::toFloat($r['summary']->netProfit))->values()) } ],
                        colors: ['#2F5FD0', '#F0A30A', '#B42318', '#157F3D'] })">
                    <div x-show="loading"><x-ui.skeleton height="300px" /></div>
                    <x-ui.empty-state x-show="empty" x-cloak :title="__('Belum ada transaksi')" :text="__('Grafik muncul setelah pemasukan atau pengeluaran tercatat.')" />
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
        <div class="col-xl-4 d-grid gap-3">
            <x-ui.card :title="__('Pemasukan per platform')">
                <div x-data="chart({ type: 'donut', height: 230, labels: @js($byPlatform->pluck('name')), series: @js($byPlatform->map(fn ($p) => F::toFloat($p->gross))), colors: @js($byPlatform->pluck('color')) })">
                    <div x-show="loading"><x-ui.skeleton height="210px" /></div>
                    <p x-show="empty" x-cloak class="text-muted fs-sm text-center py-4 mb-0">{{ __('Belum ada pemasukan.') }}</p>
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <x-ui.card :title="__('Rincian pengeluaran per kategori')">
                @php $cats = $byCategory->take(5); $rest = $byCategory->slice(5); @endphp
                <div x-data="chart({ type: 'donut', height: 240,
                        labels: @js($cats->pluck('name')->map(fn ($n) => __($n))->when($rest->isNotEmpty(), fn ($c) => $c->push(__('Lainnya')))->values()),
                        series: @js($cats->map(fn ($c) => F::toFloat($c->total))->when($rest->isNotEmpty(), fn ($c) => $c->push(F::toFloat(F::sum($rest->pluck('total')))))->values()) })">
                    <div x-show="loading"><x-ui.skeleton height="220px" /></div>
                    <p x-show="empty" x-cloak class="text-muted fs-sm text-center py-4 mb-0">{{ __('Belum ada pengeluaran valid.') }}</p>
                    <div x-ref="chart"></div>
                </div>
            </x-ui.card>
        </div>
        <div class="col-xl-4">
            <x-ui.card title="{{ __('Perhitungan :period', ['period' => $periodLabel]) }}">
                @include('reports._ledger', ['s' => $s])
            </x-ui.card>
        </div>
        <div class="col-xl-4 d-grid gap-3 align-content-start">
            @unless ($isManager)
                @include('dashboard._today')
            @endunless
            <x-ui.card :title="__('Reservasi mendatang')" flush>
                @forelse ($widgets['upcoming'] as $b)
                    <div class="d-flex gap-2 align-items-center px-4 py-2 border-bottom">
                        <span class="pm-platform-dot" style="background: {{ $b->platform->color }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-grow-1"><span class="d-block text-ink fw-semibold fs-sm text-truncate">{{ $b->guest_name ?: __('Tamu') }} · {{ $b->villa->name }}</span>
                            <span class="d-block fs-xs text-muted">{{ __(':m – :m2 · :nights malam', ['m' => $b->check_in->translatedFormat('D, d M'), 'm2' => $b->check_out->translatedFormat('d M'), 'nights' => $b->nights()]) }}</span></span>
                    </div>
                @empty
                    <p class="px-4 py-3 text-muted fs-sm mb-0">{{ __('Belum ada reservasi mendatang.') }}</p>
                @endforelse
                <div class="p-3"><a href="{{ route('bookings.calendar') }}" class="btn btn-sm btn-light w-100">{{ __('Buka kalender') }}</a></div>
            </x-ui.card>
        </div>
    </div>

    @unless ($isManager)
        <p class="fs-sm text-muted mt-3 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ __('Pengeluaran yang dihitung hanya yang sudah diperiksa (Valid) oleh Manager. Laporan resmi tersedia setelah ditandai Final.') }}</p>
    @endunless
</x-layouts.app>
