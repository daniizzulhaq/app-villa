{{-- Empty state yang membantu: ilustrasi sederhana + penjelasan + tombol aksi --}}
@props(['title', 'text' => null, 'art' => 'folder'])
<div {{ $attributes->class('pm-empty') }}>
    <svg class="pm-empty__art" viewBox="0 0 120 96" fill="none" aria-hidden="true">
        <rect x="8" y="16" width="104" height="72" rx="14" fill="#EAF0FD"/>
        @switch($art)
            @case('villa')
                <path d="M34 60 60 38l26 22" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M40 56v20h40V56" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
                <rect x="54" y="62" width="12" height="14" rx="2" fill="currentColor" opacity=".35"/>
                @break
            @case('users')
                <circle cx="50" cy="46" r="10" stroke="currentColor" stroke-width="4"/>
                <path d="M32 76c2-10 9-15 18-15s16 5 18 15" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                <circle cx="78" cy="50" r="7" fill="currentColor" opacity=".35"/>
                <path d="M70 76c1-7 5-11 11-11 5 0 9 3 11 9" stroke="currentColor" stroke-width="4" stroke-linecap="round" opacity=".35"/>
                @break
            @case('search')
                <circle cx="54" cy="50" r="16" stroke="currentColor" stroke-width="4"/>
                <path d="m66 62 12 12" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                @break
            @default
                <path d="M30 40h24l6 6h30v26a4 4 0 0 1-4 4H34a4 4 0 0 1-4-4V40Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/>
        @endswitch
        <circle cx="98" cy="20" r="6" fill="currentColor" opacity=".2"/>
        <circle cx="16" cy="84" r="4" fill="currentColor" opacity=".2"/>
    </svg>
    <h3 class="pm-empty__title">{{ $title }}</h3>
    @if ($text)<p class="pm-empty__text">{{ $text }}</p>@endif
    {{ $slot }}
</div>
