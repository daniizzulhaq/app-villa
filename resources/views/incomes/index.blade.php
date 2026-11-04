<x-layouts.app :title="__('Pemasukan')">
    <x-ui.page-header :title="__('Pemasukan')" :subtitle="__('Periode :period', ['period' => \Illuminate\Support\Carbon::parse($filters['from'])->translatedFormat('d M Y').' – '.\Illuminate\Support\Carbon::parse($filters['to'])->translatedFormat('d M Y')])">
        <x-slot:actions>
            @can('import', \App\Models\Income::class)
                <a href="{{ route('imports.create') }}" class="btn btn-light"><i class="bi bi-upload" aria-hidden="true"></i> {{ __('Import Payout') }}</a>
            @endcan
            @can('create', \App\Models\Income::class)
                <a href="{{ route('incomes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Pemasukan') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Gross booking income')" :value="\App\Support\Rupiah::format($summary->gross)" :hint="$summary->incomeCount.' transaksi'" icon="bi-cash-stack" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Komisi + potongan')" :value="\App\Support\Rupiah::format(\App\Services\Finance\FinanceCalculator::sum([$summary->platformFee, $summary->otherDeduction]))" icon="bi-scissors" icon-tone="warning" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Management fee')" :value="\App\Support\Rupiah::format($summary->managementFee)" icon="bi-percent" icon-tone="info" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Revenue after fee')" :value="\App\Support\Rupiah::format($summary->revenueAfterFee)" icon="bi-graph-up-arrow" icon-tone="success" :tone="$summary->revenueAfterFee < 0 ? 'negative' : null" /></div>
    </div>

    <x-ui.card flush>
        <form method="GET" class="pm-toolbar" role="search" data-no-lock>
            <div class="pm-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="q">{{ __('Cari') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari kode booking, tamu, catatan…') }}">
            </div>
            <select name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()">
                <option value="">{{ __('Semua villa') }}</option>
                @foreach ($villas as $id => $n)<option value="{{ $id }}" @selected((string) ($filters['villa'] ?? '') === (string) $id)>{{ $n }}</option>@endforeach
            </select>
            <select name="platform" class="form-select" aria-label="{{ __('Sumber') }}" onchange="this.form.submit()">
                <option value="">{{ __('Semua sumber') }}</option>
                @foreach ($platforms as $p)<option value="{{ $p->id }}" @selected((string) ($filters['platform'] ?? '') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control" style="flex:0 1 160px" aria-label="{{ __('Dari tanggal') }}">
            <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control" style="flex:0 1 160px" aria-label="{{ __('Sampai tanggal') }}">
            <button type="submit" class="btn btn-light"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
        </form>

        @if ($incomes->isEmpty())
            <x-ui.empty-state :title="__('Belum ada pemasukan di periode ini')" :text="__('Catat pembayaran dari reservasi atau import file payout Airbnb/Booking.com.')">
                @can('create', \App\Models\Income::class)
                    <a href="{{ route('incomes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Pemasukan') }}</a>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Tanggal bayar') }}</th><th>{{ __('Villa & tamu') }}</th><th>{{ __('Sumber') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Komisi+potongan') }}</th><th class="num">{{ __('Fee') }}</th><th class="num">{{ __('Net setelah fee') }}</th></tr></thead>
                    <tbody>
                    @foreach ($incomes as $i)
                        @php $deduct = \App\Services\Finance\FinanceCalculator::sum([$i->is_net_of_platform ? 0 : $i->platform_fee, $i->other_deduction]); @endphp
                        <tr>
                            <td data-label="{{ __('Tanggal') }}" class="text-nowrap"><a href="{{ route('incomes.show', $i) }}" class="cell-title text-decoration-none">{{ $i->payment_date->translatedFormat('d M Y') }}</a>
                                @if ($i->attachments_count)<i class="bi bi-paperclip text-muted" title="{{ __('Ada bukti bayar') }}" aria-label="{{ __('Ada bukti bayar') }}"></i>@endif</td>
                            <td data-label="{{ __('Villa & tamu') }}" class="stack-full">{{ $i->villa->name }}
                                <div class="cell-sub">{{ $i->booking?->guest_name ?: '—' }} · {{ $i->booking_ref ?: __('tanpa kode') }}</div></td>
                            <td data-label="{{ __('Sumber') }}"><span class="d-inline-flex align-items-center gap-1"><span class="pm-platform-dot" style="background: {{ $i->platform->color }}" aria-hidden="true"></span>{{ $i->platform->name }}</span>
                                @if ($i->is_net_of_platform)<div class="cell-sub">{{ __('sudah net') }}</div>@endif</td>
                            <td data-label="{{ __('Gross') }}" class="num">@rupiah($i->gross_amount)</td>
                            <td data-label="{{ __('Komisi+potongan') }}" class="num text-muted">@rupiah($deduct)</td>
                            <td data-label="{{ __('Fee') }}" class="num">@rupiah($i->fee_amount)<div class="cell-sub">{{ \App\Support\Rupiah::percent($i->fee_percentage) }}</div></td>
                            <td data-label="{{ __('Net setelah fee') }}" class="num fw-semibold text-ink">@rupiah(\Brick\Math\BigDecimal::of($i->net_revenue)->minus($i->fee_amount))</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($incomes->hasPages())<div class="p-3 border-top">{{ $incomes->onEachSide(1)->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
