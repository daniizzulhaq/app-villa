@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => __('Kembali')])
<div {{ $attributes->class('pm-page-header') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="pm-page-header__back"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $backLabel }}</a>
        @endif
        <h1 class="pm-page-header__title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="pm-page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="pm-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
