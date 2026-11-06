@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => __('Kembali'), 'icon' => null])
<div {{ $attributes->class('pm-page-header') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="pm-page-header__back"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $backLabel }}</a>
        @endif
        <div class="pm-page-header__heading">
            @if ($icon)<span class="pm-module-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>@endif
            <div class="min-w-0">
                <h1 class="pm-page-header__title">{{ $title }}</h1>
                @if ($subtitle)<p class="pm-page-header__subtitle">{{ $subtitle }}</p>@endif
            </div>
        </div>
    </div>
    @isset($actions)
        <div class="pm-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
