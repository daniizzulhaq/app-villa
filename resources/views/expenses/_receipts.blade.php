{{-- Galeri foto nota + lightbox sederhana. $receipts: koleksi ExpenseReceipt --}}
<div x-data="{ open: null }">
    <div class="pm-gallery">
        @foreach ($receipts as $r)
            @if (str_ends_with($r->path, '.pdf'))
                <a href="{{ route('files.receipt', $r) }}" target="_blank" rel="noopener" class="pm-thumb d-grid text-muted text-decoration-none" style="place-items:center">
                    <span class="text-center"><i class="bi bi-file-earmark-pdf fs-1 d-block" aria-hidden="true"></i><span class="fs-xs">{{ $r->original_name }}</span></span>
                </a>
            @else
                <button type="button" class="pm-thumb p-0" @click="open = @js(route('files.receipt', $r))" aria-label="{{ __('Perbesar foto nota :n', ['n' => $loop->iteration]) }}">
                    <img src="{{ route('files.receipt', $r) }}" alt="{{ __('Foto nota :n', ['n' => $loop->iteration]) }}" loading="lazy">
                    <span class="pm-thumb__cap">{{ __(':size KB', ['size' => number_format($r->size / 1024, 0, ',', '.')]) }}</span>
                </button>
            @endif
        @endforeach
    </div>
    <div x-cloak x-show="open" class="pm-modal-backdrop" style="background: rgba(15,27,45,.9)" @click.self="open = null" @keydown.escape.window="open = null">
        <div class="pm-lightbox" role="dialog" aria-modal="true" aria-label="{{ __('Foto nota ukuran penuh') }}" x-trap.noscroll="open">
            <img :src="open" alt="{{ __('Foto nota ukuran penuh') }}">
            <div class="pm-lightbox__actions">
                <a :href="open" target="_blank" rel="noopener" class="btn btn-light"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> {{ __('Buka gambar asli') }}</a>
                <button type="button" class="btn btn-light" @click="open = null">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
