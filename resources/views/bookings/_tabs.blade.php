<nav class="pm-tabs mb-3 px-0 bg-transparent" aria-label="{{ __('Tampilan reservasi') }}">
    <a href="{{ route('bookings.calendar', array_filter(['villa' => request('villa')])) }}" @class(['active' => request()->routeIs('bookings.calendar')])><i class="bi bi-calendar3" aria-hidden="true"></i> {{ __('Kalender') }}</a>
    <a href="{{ route('bookings.index', array_filter(['villa' => request('villa')])) }}" @class(['active' => request()->routeIs('bookings.index')])>
        <i class="bi bi-list-ul" aria-hidden="true"></i> {{ __('Daftar') }}
        @if (($needsCompletion ?? 0) > 0)<span class="count" style="background: var(--pm-warning-soft); color: var(--pm-warning)">{{ __(':needscompletion perlu dilengkapi', ['needscompletion' => $needsCompletion]) }}</span>@endif
    </a>
</nav>
