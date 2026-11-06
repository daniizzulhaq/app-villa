@props(['reset', 'advancedKeys' => []])
@php $activeAdvanced = collect($advancedKeys)->filter(fn ($key) => request()->filled($key))->count(); @endphp
<form method="GET" {{ $attributes->class('pm-filters') }} role="search" data-no-lock>
    <div class="pm-filters__main">{{ $slot }}</div>
    @isset($advanced)
        <details class="pm-filters__advanced" @if($activeAdvanced) open @endif>
            <summary><i class="bi bi-sliders2" aria-hidden="true"></i>{{ __('Filter tambahan') }}@if($activeAdvanced)<span class="pm-badge pm-badge--primary pm-badge--plain">{{ $activeAdvanced }}</span>@endif<i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i></summary>
            <div class="pm-filters__fields">{{ $advanced }}</div>
        </details>
    @endisset
    <div class="pm-filters__actions">
        <span><i class="bi bi-info-circle" aria-hidden="true"></i>{{ __('Atur filter, lalu ketuk Terapkan.') }}</span>
        <a href="{{ $reset }}" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>{{ __('Reset') }}</a>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search" aria-hidden="true"></i>{{ __('Terapkan') }}</button>
    </div>
</form>
