{{-- Placeholder loading. <x-ui.skeleton lines="3" /> atau <x-ui.skeleton height="240px" /> --}}
@props(['lines' => 1, 'height' => null])
<div {{ $attributes->class('d-grid gap-2') }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <span class="pm-skeleton" style="height: {{ $height ?? '14px' }}; width: {{ $height ? '100%' : ($i === $lines - 1 && $lines > 1 ? '60%' : '100%') }}"></span>
    @endfor
</div>
