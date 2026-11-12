<x-layouts.app :title="__('Laporan :period', ['period' => $periodLabel])">
    @php
        $user = auth()->user();
        $s = $summary;
        $isMonthly = $type === 'monthly';
        $params = array_filter(['villa' => $villaId, 'year' => $year, 'month' => $month, 'type' => $type]);
        $prev = $isMonthly ? $from->copy()->subMonth() : $from->copy()->subYear();
        $next = $isMonthly ? $from->copy()->addMonth() : $from->copy()->addYear();
        $route = $isMonthly ? 'reports.monthly' : 'reports.annual';
        $nav = fn ($d) => route($route, array_filter(['villa' => $villaId, 'year' => $d->year, 'month' => $isMonthly ? $d->month : null]));
    @endphp

    <x-ui.page-header :title="($isMonthly ? __('Laporan Bulanan') : __('Laporan Tahunan')).' · '.$periodLabel"
                      :subtitle="$villa ? $villa->name.($villa->owner ? ' · '.__('Owner: :name', ['name' => $villa->owner->name]) : '') : __('Gabungan :count villa', ['count' => $villas->count()])"
                      :back="route('reports.index', ['year' => $year])" :back-label="__('Semua laporan')">
        <x-slot:actions>
            <a href="{{ route('reports.excel', $params) }}" class="btn btn-light" data-no-lock><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> {{ __('Export Excel') }}</a>
            <a href="{{ route('reports.pdf', $params) }}" class="btn btn-primary"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> {{ __('Export PDF') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($incomes->contains(fn ($income) => $income->isFromHostex()))
        <div class="alert alert-info fs-sm">{{ __('Includes automatic Hostex booking revenue; payouts are not verified.') }}</div>
    @endif

    {{-- Pilih periode & villa --}}
    <x-ui.card flush class="mb-3">
        <form method="GET" action="{{ route($route) }}" class="pm-toolbar pm-report-filters border-0" data-no-lock>
            <a href="{{ $nav($prev) }}" class="btn btn-light btn-icon pm-report-filters__prev" aria-label="{{ __('Periode sebelumnya') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <div @class(['pm-report-filters__period', 'pm-report-filters__period--annual' => ! $isMonthly])>
                @if ($isMonthly)
                    <div>
                        <label for="reportMonth" class="pm-report-filters__label">{{ __('Bulan') }}</label>
                        <select id="reportMonth" name="month" class="form-select" aria-label="{{ __('Bulan') }}" onchange="this.form.submit()">
                            @for ($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($m === $month)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>@endfor
                        </select>
                    </div>
                @endif
                <div>
                    <label for="reportYear" class="pm-report-filters__label">{{ __('Tahun') }}</label>
                    <select id="reportYear" name="year" class="form-select" aria-label="{{ __('Tahun') }}" onchange="this.form.submit()">
                        @for ($y = max(today()->year + 1, $year); $y >= min(today()->year - 5, $year); $y--)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endfor
                    </select>
                </div>
            </div>
            <a href="{{ $nav($next) }}" class="btn btn-light btn-icon pm-report-filters__next" aria-label="{{ __('Periode berikutnya') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <div class="pm-report-filters__villa">
                <label for="reportVilla" class="pm-report-filters__label">{{ __('Villa') }}</label>
                <select id="reportVilla" name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()">
                    <option value="">{{ __('Semua villa (gabungan)') }}</option>
                    @foreach ($villaOptions as $id => $n)<option value="{{ $id }}" @selected($villaId === $id)>{{ $n }}</option>@endforeach
                </select>
            </div>
            <a href="{{ $isMonthly ? route('reports.annual', array_filter(['villa' => $villaId, 'year' => $year])) : route('reports.monthly', array_filter(['villa' => $villaId, 'year' => $year, 'month' => 1])) }}" class="btn btn-ghost pm-report-filters__mode">
                {{ $isMonthly ? __('Lihat tahunan') : __('Lihat bulanan') }}
            </a>
        </form>
    </x-ui.card>

    {{-- Status Final / kunci --}}
    @if ($isMonthly && $villa && $user->isManager())
        @php $isFinal = $monthStatus?->status === \App\Enums\ReportStatus::Final; @endphp
        <div class="pm-card pm-card__body mb-3 d-flex flex-wrap gap-3 align-items-center" x-data="{ unlocking: false }">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <x-ui.badge :variant="$isFinal ? 'info' : 'neutral'">{{ $isFinal ? __('Final') : __('Draft') }}</x-ui.badge>
                    @if ($isFinal)<span class="fs-sm text-muted">{{ __('oleh :name, :i · revisi :revision', ['name' => $monthStatus->finalizer?->name, 'i' => $monthStatus->finalized_at?->translatedFormat('d M Y H:i'), 'revision' => $monthStatus->revision]) }}</span>@endif
                </div>
                @if ($isFinal)
                    <div class="fs-sm text-muted"><i class="bi bi-lock" aria-hidden="true"></i> {{ __('Bulan terkunci — pemasukan & pengeluaran bulan ini tidak bisa diubah.') }}</div>
                @else
                    @foreach ($blockers ?? [] as $b)<div class="fs-sm text-negative"><i class="bi bi-x-circle" aria-hidden="true"></i> {{ $b }}</div>@endforeach
                    @foreach ($warnings ?? [] as $w)<div class="fs-sm" style="color: var(--pm-warning)"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $w }}</div>@endforeach
                    @if (empty($blockers) && empty($warnings))<div class="fs-sm text-muted">{{ __('Periksa angka di bawah. Setelah Final, Owner bisa melihat & mengunduh laporan ini.') }}</div>@endif
                @endif
                @error('report')<div class="fs-sm text-negative mt-1">{{ $message }}</div>@enderror
                @if ($monthStatus?->unlock_reason && ! $isFinal)<div class="fs-xs text-muted mt-1">{{ __('Terakhir dibuka kuncinya: ":unlock_reason" (:i)', ['unlock_reason' => $monthStatus->unlock_reason, 'i' => $monthStatus->unlocked_at?->translatedFormat('d M Y H:i')]) }}</div>@endif
            </div>
            @if ($isFinal && ! $user->isMaster())
                <span class="fs-xs text-muted"><i class="bi bi-lock" aria-hidden="true"></i> {{ __('Hanya Master yang dapat membuka kunci.') }}</span>
            @elseif ($isFinal)
                <button type="button" class="btn btn-light" @click="unlocking = !unlocking" x-show="!unlocking"><i class="bi bi-unlock" aria-hidden="true"></i> {{ __('Buka Kunci') }}</button>
                <form method="POST" action="{{ route('reports.unlock', $monthStatus) }}" class="w-100 d-flex flex-wrap gap-2" x-show="unlocking" x-cloak data-validate novalidate>
                    @csrf
                    <div class="flex-grow-1" data-field>
                        <label class="visually-hidden" for="unlock_reason">{{ __('Alasan') }}</label>
                        <input id="unlock_reason" name="unlock_reason" class="form-control" required minlength="5" maxlength="255" placeholder="{{ __('Alasan membuka kunci (tercatat di audit log)') }}" data-label="{{ __('Alasan') }}">
                    </div>
                    <button type="button" class="btn btn-light" @click="unlocking = false">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Buka Kunci') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('reports.finalize') }}" data-confirm="{{ __('Tandai laporan :period sebagai Final?', ['period' => $periodLabel]) }}"
                      data-confirm-text="{{ __('Bulan ini akan dikunci dan Owner diberi tahu. Perubahan setelahnya harus membuka kunci dulu (tercatat di audit log).') }}" data-confirm-button="{{ __('Ya, tandai Final') }}">
                    @csrf
                    <input type="hidden" name="villa" value="{{ $villa->id }}"><input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit" class="btn btn-primary" @disabled(! empty($blockers))><i class="bi bi-patch-check" aria-hidden="true"></i> {{ __('Tandai Final') }}</button>
                </form>
            @endif
        </div>
    @endif

    @if ($restricted && ! $hasFinal)
        <x-ui.card>
            <x-ui.empty-state :title="__('Laporan belum Final')" :text="__('Manager sedang memeriksa laporan periode ini. Anda akan mendapat notifikasi saat laporan sudah Final.')" />
        </x-ui.card>
    @else
        @if ($restricted)
            <div class="alert alert-info d-flex gap-2"><i class="bi bi-info-circle" aria-hidden="true"></i><div class="fs-sm">{{ __('Hanya menampilkan bulan yang laporannya sudah') }} <strong>{{ __('Final') }}</strong>.</div></div>
        @endif

        {{-- Angka utama --}}
        <div class="row g-3 mb-3">
            <div class="col-lg-4"><x-ui.stat hero :label="__('Final net profit')" :value="\App\Support\Rupiah::format($s->netProfit)" :tone="$s->isLoss() ? 'negative' : 'positive'" :hint="__('Margin :margin dari gross', ['margin' => $s->marginLabel()])" icon="bi-trophy" /></div>
            <div class="col-6 col-lg-2"><x-ui.stat :label="__('Gross income')" :value="\App\Support\Rupiah::short($s->gross)" :hint="$s->incomeCount.' pemasukan'" /></div>
            <div class="col-6 col-lg-2"><x-ui.stat :label="__('Management fee')" :value="\App\Support\Rupiah::short($s->managementFee)" :hint="__('View fee details')" href="#management-fees" /></div>
            <div class="col-6 col-lg-2"><x-ui.stat :label="__('Pengeluaran')" :value="\App\Support\Rupiah::short($s->expenses)" :hint="__(':count nota valid', ['count' => $s->expenseCount])" /></div>
            <div class="col-6 col-lg-2"><x-ui.stat :label="__('Margin')" :value="$s->marginLabel()" :tone="$s->margin() === null ? null : ($s->isLoss() ? 'negative' : 'positive')" /></div>
        </div>

        @include('reports._management-fees')

        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <x-ui.card :title="__('Perhitungan')">
                    @include('reports._ledger', ['s' => $s])
                    <div class="mt-3 fs-sm">
                        <div class="label-caps mb-1">{{ __('Basis management fee') }}</div>
                        @foreach ($feeInfo as $vid => $label)
                            <div>@if (! $villa)<strong>{{ $villas->firstWhere('id', $vid)?->name }}:</strong> @endif{{ $label }}</div>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>
            <div class="col-lg-7 d-grid gap-3">
                <x-ui.card :title="__('Pemasukan per platform')">
                    @php $pf = $byPlatform; @endphp
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div x-data="chart({ type: 'donut', height: 240, labels: @js($pf->pluck('name')), series: @js($pf->map(fn ($p) => \App\Services\Finance\FinanceCalculator::toFloat($p->gross))), colors: @js($pf->pluck('color')) })">
                                <div x-show="loading"><x-ui.skeleton height="220px" /></div>
                                <p x-show="empty" x-cloak class="text-muted fs-sm text-center py-5 mb-0">{{ __('Belum ada pemasukan.') }}</p>
                                <div x-ref="chart"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="table-responsive">
                            <table class="table table-sm fs-sm mb-0">
                                <thead><tr><th>{{ __('Platform') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Fee') }}</th></tr></thead>
                                @forelse ($pf as $p)
                                    <tr><td><span class="pm-platform-dot me-1" style="background: {{ $p->color }}" aria-hidden="true"></span>{{ $p->name }}</td><td class="text-end num">@rupiah($p->gross)</td><td class="text-end num">@rupiah($p->fee_amount)</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">—</td></tr>
                                @endforelse
                            </table>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
                <x-ui.card :title="__('Pengeluaran per kategori')">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div x-data="chart({ type: 'donut', height: 240, labels: @js($byCategory->take(6)->pluck('name')->push($byCategory->count() > 6 ? 'Lainnya' : null)->filter()->values()),
                                series: @js($byCategory->take(6)->map(fn ($c) => \App\Services\Finance\FinanceCalculator::toFloat($c->total))->push($byCategory->count() > 6 ? \App\Services\Finance\FinanceCalculator::toFloat(\App\Services\Finance\FinanceCalculator::sum($byCategory->slice(6)->pluck('total'))) : null)->filter(fn ($v) => $v !== null)->values()) })">
                                <div x-show="loading"><x-ui.skeleton height="220px" /></div>
                                <p x-show="empty" x-cloak class="text-muted fs-sm text-center py-5 mb-0">{{ __('Belum ada pengeluaran valid.') }}</p>
                                <div x-ref="chart"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="table-responsive">
                            <table class="table table-sm fs-sm mb-0">
                                @forelse ($byCategory as $c)
                                    <tr><td>{{ __($c->name) }}</td><td class="text-end num">@rupiah($c->total)</td></tr>
                                @empty
                                    <tr><td class="text-muted">—</td></tr>
                                @endforelse
                            </table>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>

        @if (! $isMonthly && $series->isNotEmpty())
            <x-ui.card :title="__('Per bulan')" :subtitle="__('Pemasukan vs management fee vs pengeluaran vs laba')" class="mb-3">
                <div x-data="chart({ type: 'bar', height: 300, labels: @js($series->pluck('label')->values()), series: [
                        { name: @js(__('Gross')), data: @js($series->map(fn ($r) => \App\Services\Finance\FinanceCalculator::toFloat($r['summary']->gross))->values()) },
                        { name: @js(__('Management fee')), data: @js($series->map(fn ($r) => \App\Services\Finance\FinanceCalculator::toFloat($r['summary']->managementFee))->values()) },
                        { name: @js(__('Pengeluaran')), data: @js($series->map(fn ($r) => \App\Services\Finance\FinanceCalculator::toFloat($r['summary']->expenses))->values()) },
                        { name: @js(__('Net profit')), data: @js($series->map(fn ($r) => \App\Services\Finance\FinanceCalculator::toFloat($r['summary']->netProfit))->values()) } ],
                        colors: ['#aac29b', '#c2a572', '#d9c5ab', '#285b49'] })">
                    <div x-show="loading"><x-ui.skeleton height="280px" /></div>
                    <p x-show="empty" x-cloak class="text-muted text-center py-5 mb-0">{{ __('Belum ada data.') }}</p>
                    <div x-ref="chart"></div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table pm-table pm-table--stack fs-sm">
                        <thead><tr><th>{{ __('Bulan') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Komisi+potongan') }}</th><th class="num">{{ __('Mgmt fee') }}</th><th class="num">{{ __('Pengeluaran') }}</th><th class="num">{{ __('Net profit') }}</th><th class="num">{{ __('Margin') }}</th></tr></thead>
                        <tbody>
                        @foreach ($series as $row)
                            @php $x = $row['summary']; @endphp
                            <tr>
                                <td class="stack-full cell-title">@if($villaId)<a href="{{ route('reports.monthly', ['villa' => $villaId, 'year' => $row['from']->year, 'month' => $row['from']->month]) }}">{{ $row['label'] }}</a>@else{{ $row['label'] }}@endif</td>
                                <td data-label="{{ __('Gross') }}" class="num">@rupiah($x->gross)</td>
                                <td data-label="{{ __('Komisi+potongan') }}" class="num">@rupiah(\App\Services\Finance\FinanceCalculator::sum([$x->platformFee, $x->otherDeduction]))</td>
                                <td data-label="{{ __('Mgmt fee') }}" class="num">@rupiah($x->managementFee)</td>
                                <td data-label="{{ __('Pengeluaran') }}" class="num">@rupiah($x->expenses)</td>
                                <td data-label="{{ __('Net profit') }}" class="num fw-semibold {{ $x->isLoss() ? 'text-negative' : '' }}">@rupiah($x->netProfit)</td>
                                <td data-label="{{ __('Margin') }}" class="num">{{ $x->marginLabel() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        @if (! $villa && $byVilla->isNotEmpty())
            <x-ui.card :title="__('Per villa')" flush class="mb-3">
                <div class="table-responsive"><table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Villa') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Mgmt fee') }}</th><th class="num">{{ __('Pengeluaran') }}</th><th class="num">{{ __('Net profit') }}</th><th class="num">{{ __('Margin') }}</th></tr></thead>
                    <tbody>
                    @foreach ($byVilla as $row)
                        @php $x = $row['summary']; @endphp
                        <tr>
                            <td class="stack-full cell-title"><a href="{{ route($route, array_filter(['villa' => $row['villa']->id, 'year' => $year, 'month' => $month])) }}">{{ $row['villa']->name }}</a></td>
                            <td data-label="{{ __('Gross') }}" class="num">@rupiah($x->gross)</td>
                            <td data-label="{{ __('Mgmt fee') }}" class="num">@rupiah($x->managementFee)</td>
                            <td data-label="{{ __('Pengeluaran') }}" class="num">@rupiah($x->expenses)</td>
                            <td data-label="{{ __('Net profit') }}" class="num fw-semibold {{ $x->isLoss() ? 'text-negative' : '' }}">@rupiah($x->netProfit)</td>
                            <td data-label="{{ __('Margin') }}" class="num">{{ $x->marginLabel() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </x-ui.card>
        @endif

        <div class="row g-3">
            <div class="col-xl-6">
                <x-ui.card :title="__('Daftar pemasukan')" :subtitle="__(':count transaksi', ['count' => $incomes->count()])" flush>
                    <div class="table-responsive" style="max-height: 480px">
                        <table class="table pm-table pm-table--stack fs-sm">
                            <thead><tr><th>{{ __('Tanggal') }}</th><th>{{ __('Tamu / kode') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Fee') }}</th></tr></thead>
                            <tbody>
                            @forelse ($incomes as $i)
                                <tr>
                                    <td data-label="{{ __('Tanggal') }}">{{ $i->payment_date->translatedFormat('d M') }}<div class="cell-sub">{{ $i->platform->name }}</div></td>
                                    <td data-label="{{ __('Tamu') }}">{{ $i->booking?->guest_name ?: '—' }}<div class="cell-sub">{{ $i->booking_ref ?: '' }} @unless($villa)· {{ $i->villa->name }}@endunless</div></td>
                                    <td data-label="{{ __('Gross') }}" class="num">@rupiah($i->gross_amount)</td>
                                    <td data-label="{{ __('Fee') }}" class="num">@rupiah($i->fee_amount)</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
            <div class="col-xl-6">
                <x-ui.card :title="__('Daftar pengeluaran (Valid)')" :subtitle="__(':count transaksi · ref. nota untuk dicocokkan dengan foto', ['count' => $expenses->count()])" flush>
                    <div class="table-responsive" style="max-height: 480px">
                        <table class="table pm-table pm-table--stack fs-sm">
                            <thead><tr><th>{{ __('Tanggal') }}</th><th>{{ __('Keterangan') }}</th><th class="num">{{ __('Nominal') }}</th><th>{{ __('Nota') }}</th></tr></thead>
                            <tbody>
                            @forelse ($expenses as $e)
                                <tr>
                                    <td data-label="{{ __('Tanggal') }}">{{ $e->expense_date->translatedFormat('d M') }}</td>
                                    <td data-label="{{ __('Keterangan') }}">{{ $e->description }}<div class="cell-sub">{{ __($e->category->name) }} @unless($villa)· {{ $e->villa->name }}@endunless</div></td>
                                    <td data-label="{{ __('Nominal') }}" class="num">@rupiah($e->amount)</td>
                                    <td data-label="{{ __('Nota') }}"><a href="{{ route('expenses.show', $e) }}" class="text-nowrap">{{ __('NOTA-:str_pad_left', ['str_pad_left' => str_pad($e->id, 5, '0', STR_PAD_LEFT)]) }}</a><div class="cell-sub">{{ __(':count foto', ['count' => $e->receipts->count()]) }}</div></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">{{ __('Tidak ada pengeluaran valid.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>
    @endif
</x-layouts.app>
