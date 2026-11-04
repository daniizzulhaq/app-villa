<x-layouts.app :title="__('Pencarian')">
    <x-ui.page-header :title="__('Pencarian')" :subtitle="$q ? __(':count hasil untuk “:q”', ['count' => $total, 'q' => $q]) : __('Cari villa, kode booking, tamu, pemasukan, pengeluaran, vendor, kategori, atau periode (mis. Oktober 2026).')" />

    <form method="GET" action="{{ route('search') }}" class="pm-card pm-toolbar mb-3" role="search" data-no-lock>
        <div class="pm-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="sq">{{ __('Kata kunci') }}</label>
            <input type="search" id="sq" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('Ketik minimal 2 huruf…') }}" autofocus minlength="2">
        </div>
        <button class="btn btn-primary" type="submit">{{ __('Cari') }}</button>
    </form>

    @if ($q && mb_strlen($q) < 2)
        <p class="text-muted">{{ __('Ketik minimal 2 huruf.') }}</p>
    @elseif ($q && ! $total)
        <x-ui.card><x-ui.empty-state art="search" :title="__('Tidak ditemukan')" :text="__('Coba kata kunci lain: nama tamu, kode booking (mis. HM…), nama vendor, atau periode seperti “2026-10”.')" /></x-ui.card>
    @endif

    <div class="row g-3">
        @foreach ($groups as $label => $items)
            <div class="col-lg-6">
                <x-ui.card :title="$label" :subtitle="count($items).' hasil'" flush>
                    @foreach ($items as $it)
                        <a href="{{ $it['url'] }}" class="d-flex gap-3 align-items-center px-4 py-3 border-bottom text-decoration-none">
                            <span class="pm-stat__icon flex-shrink-0" aria-hidden="true"><i class="bi {{ $it['icon'] }}"></i></span>
                            <span class="min-w-0"><span class="d-block fw-semibold text-ink text-truncate">{{ $it['title'] }}</span><span class="d-block fs-sm text-muted text-truncate">{{ $it['sub'] }}</span></span>
                            <i class="bi bi-chevron-right ms-auto text-muted" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </x-ui.card>
            </div>
        @endforeach
    </div>
</x-layouts.app>
