{{-- Bar aksi form. Di HP menempel di atas bottom nav agar tombol Simpan selalu terjangkau. --}}
@props(['cancel' => null, 'submit' => __('Simpan'), 'icon' => 'bi-check-lg', 'loading' => __('Menyimpan…')])
<div {{ $attributes->class('pm-form-actions') }}>
    @if ($cancel)
        <a href="{{ $cancel }}" class="btn btn-light">{{ __('Batal') }}</a>
    @endif
    {{ $slot }}
    <button type="submit" class="btn btn-primary" data-loading-text="{{ $loading }}">
        <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $submit }}
    </button>
</div>
