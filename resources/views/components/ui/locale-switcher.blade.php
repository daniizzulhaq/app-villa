{{--
  Pemilih bahasa. <x-ui.locale-switcher />  atau  <x-ui.locale-switcher variant="list" /> (menu HP)
--}}
@props(['variant' => 'dropdown'])
@php
    $locales = config('propertyme.locales');
    $current = app()->getLocale();
    $short = fn ($code) => strtoupper(substr($code, 0, 2));
@endphp

@if ($variant === 'list')
    <form method="POST" action="{{ route('locale.update') }}" data-no-lock {{ $attributes->merge(['class' => 'pm-locale-list']) }}>
        @csrf
        <label for="pmLocaleSelect" class="form-label fs-sm text-muted mb-1"><i class="bi bi-translate" aria-hidden="true"></i> {{ __('Bahasa') }}</label>
        <select id="pmLocaleSelect" name="locale" class="form-select" onchange="this.form.submit()">
            @foreach ($locales as $code => $name)
                <option value="{{ $code }}" @selected($code === $current) lang="{{ str_replace('_', '-', $code) }}">{{ $name }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="btn btn-light btn-sm mt-2">OK</button></noscript>
    </form>
@else
    <div {{ $attributes->class('dropdown') }}>
        <button class="btn btn-ghost px-2 d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="{{ __('Bahasa') }}: {{ $locales[$current] ?? $current }}">
            <i class="bi bi-translate" aria-hidden="true"></i>
            <span class="fs-sm fw-semibold">{{ $short($current) }}</span>
        </button>
        <form method="POST" action="{{ route('locale.update') }}" class="dropdown-menu dropdown-menu-end" data-no-lock>
            @csrf
            @foreach ($locales as $code => $name)
                <button type="submit" name="locale" value="{{ $code }}" lang="{{ str_replace('_', '-', $code) }}"
                        @class(['dropdown-item d-flex align-items-center gap-2', 'active' => $code === $current])
                        @if ($code === $current) aria-current="true" @endif>
                    <span class="badge text-bg-light border fw-semibold" style="min-width:2.2rem">{{ $short($code) }}</span>
                    {{ $name }}
                </button>
            @endforeach
        </form>
    </div>
@endif
