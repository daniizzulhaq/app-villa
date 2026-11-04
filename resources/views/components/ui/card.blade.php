@props(['title' => null, 'subtitle' => null, 'flush' => false])
<section {{ $attributes->class('pm-card') }}>
    @if ($title || isset($actions))
        <header class="pm-card__header">
            <div class="min-w-0">
                @if ($title)<h2 class="pm-card__title">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="pm-card__subtitle">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="d-flex gap-2 flex-shrink-0">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['pm-card__body' => ! $flush, 'pm-card__body--flush' => $flush])>
        {{ $slot }}
    </div>
    @isset($footer)
        {{ $footer }}
    @endisset
</section>
