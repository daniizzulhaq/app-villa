<x-layouts.guest :title="$code.' · '.$heading">
    <main class="min-vh-100 d-grid place-items-center p-4" style="place-items:center">
        <div class="pm-card pm-card__body text-center" style="max-width:440px">
            <div class="pm-modal__icon mx-auto {{ $code >= 500 ? 'pm-modal__icon--danger' : '' }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></div>
            <div class="label-caps mb-1">{{ __('Kode :code', ['code' => $code]) }}</div>
            <h1 class="h4 mb-2">{{ $heading }}</h1>
            <p class="text-muted mb-4">{{ $text }}</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-light"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('Kembali') }}</a>
                <a href="{{ url('/') }}" class="btn btn-primary"><i class="bi bi-house" aria-hidden="true"></i> {{ __('Beranda') }}</a>
            </div>
        </div>
    </main>
</x-layouts.guest>
