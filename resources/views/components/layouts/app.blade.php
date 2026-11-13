@props(['title' => null, 'fab' => null])

@php
    $user = auth()->user();
    $sidebar = \App\Support\Navigation::sidebar($user);
    $bottom = \App\Support\Navigation::bottom($user);
    // Tombol kamera mengambang: default tampil untuk Staff
    $showFab = $fab ?? $user->isStaff();
    $uploadUrl = Route::has('expenses.create') ? route('expenses.create', ['camera' => 1]) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/propertyme-logo.png') }}">
    @include('layouts.partials.i18n')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
<a href="#main" class="skip-link">{{ __('Langsung ke konten') }}</a>

{{-- ============ Sidebar (desktop) ============ --}}
<aside class="pm-sidebar" aria-label="{{ __('Menu utama') }}">
    <a href="{{ $user->homeUrl() }}" class="pm-brand" aria-label="{{ __('Beranda PropertyMe') }}">
        <x-ui.brand-mark />
        <span class="pm-brand__sub">VILLA MANAGEMENT</span>
    </a>
    <div class="pm-workspace"><span class="pm-workspace__icon"><i class="bi bi-buildings" aria-hidden="true"></i></span><span><strong>{{ __('Ruang kerja villa') }}</strong><small>{{ __('Operasional & keuangan') }}</small></span></div>
    @include('layouts.partials.nav-list', ['groups' => $sidebar])
    <a class="pm-sidebar__note" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear" aria-hidden="true"></i><span>{{ __('Ruang kerja Anda') }}<small>{{ __('Kelola profil & preferensi') }}</small></span><i class="bi bi-arrow-up-right ms-auto" aria-hidden="true"></i></a>
    <div class="pm-sidebar__user">
        <x-ui.avatar :user="$user" />
        <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold text-ink text-truncate">{{ $user->name }}</div>
            <div class="fs-xs text-muted">{{ $user->roleEnum()?->shortLabel() }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}" data-no-lock>
            @csrf
            <button type="submit" class="btn btn-ghost btn-icon" data-bs-toggle="tooltip" title="{{ __('Keluar') }}" aria-label="{{ __('Keluar') }}">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</aside>

<div class="pm-main">
    {{-- ============ Top bar ============ --}}
    <header class="pm-topbar">
        <a href="{{ $user->homeUrl() }}" class="pm-brand p-0 d-lg-none" aria-label="{{ __('Beranda PropertyMe') }}">
            <x-ui.brand-mark />
        </a>
        <span class="pm-topbar__title d-lg-none">{{ $title ?? config('app.name') }}</span>
        <div class="pm-breadcrumb d-none d-xl-flex" aria-label="{{ __('Lokasi halaman') }}"><i class="bi bi-house-door" aria-hidden="true"></i><span>/</span><strong>{{ $title ?? __('Dashboard') }}</strong></div>

        {{-- Pencarian global (desktop: kolom; HP: ikon). Tekan "/" untuk fokus. --}}
        <form method="GET" action="{{ route('search') }}" class="pm-topsearch d-none d-lg-block" role="search" data-no-lock
              x-data @keydown.window.slash="if (!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.q.focus() }">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="topq" class="visually-hidden">{{ __('Cari') }}</label>
            <input id="topq" x-ref="q" type="search" name="q" class="form-control" placeholder="{{ __('Cari villa, tamu, kode booking, vendor…') }}" value="{{ request()->routeIs('search') ? request('q') : '' }}" autocomplete="off">
            <kbd aria-hidden="true">/</kbd>
        </form>

        <div class="ms-auto d-flex align-items-center gap-1">
            {{ $topbar ?? '' }}
            <x-ui.locale-switcher class="d-none d-md-block" />
            <a href="{{ route('search') }}" class="btn btn-ghost btn-icon d-lg-none" aria-label="{{ __('Cari') }}"><i class="bi bi-search" aria-hidden="true"></i></a>

            <x-ui.notification-menu :category="\App\Enums\NotificationCategory::Receipt" />
            <x-ui.notification-menu :category="\App\Enums\NotificationCategory::General" />
            <div class="dropdown">
                <button class="btn btn-ghost px-2 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Menu akun') }}">
                    <x-ui.avatar :user="$user" />
                    <span class="d-none d-md-inline text-start lh-sm">
                        <span class="d-block fw-semibold text-ink fs-sm">{{ $user->name }}</span>
                        <span class="d-block fs-xs text-muted fw-normal">{{ $user->roleEnum()?->shortLabel() }}</span>
                    </span>
                    <i class="bi bi-chevron-down fs-xs text-muted d-none d-md-inline" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person" aria-hidden="true"></i> {{ __('Profil saya') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="bi bi-bell" aria-hidden="true"></i> {{ __('Notifikasi') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('notifications.index', ['category' => 'receipt']) }}"><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Receipt') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" data-no-lock>
                            @csrf
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> {{ __('Keluar') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main id="main" class="pm-content" tabindex="-1">
        {{ $slot }}
    </main>
    <footer class="pm-footer"><span>© {{ date('Y') }} PropertyMe</span><span>{{ __('Dikelola dengan tenang. Tumbuh dengan terarah.') }} <i class="bi bi-flower2" aria-hidden="true"></i></span></footer>
</div>

{{-- ============ Bottom navigation (HP) ============ --}}
<nav class="pm-bottomnav d-lg-none" aria-label="{{ __('Navigasi bawah') }}">
    @foreach ($bottom as $item)
        @if (! empty($item['menu']))
            <button type="button" data-bs-toggle="offcanvas" data-bs-target="#pmMenu" aria-controls="pmMenu">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
            </button>
        @elseif ($item['available'])
            <a href="{{ $item['url'] }}" @class(['active' => $item['active']]) @if($item['active']) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>@if ($item['count'])<span class="pm-dot" aria-label="{{ $item['count'] }} perlu tindakan"></span>@endif
            </a>
        @else
            <span class="disabled" aria-disabled="true" title="{{ __('Segera hadir') }}">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
            </span>
        @endif
    @endforeach
</nav>

{{-- Menu lengkap (HP) --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="pmMenu" aria-labelledby="pmMenuLabel">
    <div class="offcanvas-header border-bottom">
        <div class="d-flex align-items-center gap-2">
            <x-ui.avatar :user="$user" />
            <div>
                <div id="pmMenuLabel" class="fw-semibold text-ink">{{ $user->name }}</div>
                <div class="fs-xs text-muted">{{ $user->roleEnum()?->shortLabel() }}</div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Tutup menu') }}"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        @include('layouts.partials.nav-list', ['groups' => $sidebar])
        <div class="p-3 border-top">
            <x-ui.locale-switcher variant="list" class="mb-3" />
            <form method="POST" action="{{ route('logout') }}" data-no-lock>
                @csrf
                <button class="btn btn-light w-100" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> {{ __('Keluar') }}</button>
            </form>
        </div>
    </div>
</div>

{{-- Tombol kamera mengambang (Staff): satu ketukan menuju upload nota --}}
@if ($showFab)
    @if ($uploadUrl)
        <a href="{{ $uploadUrl }}" class="pm-fab"><i class="bi bi-camera-fill" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
    @else
        <span class="pm-fab disabled" aria-disabled="true" title="{{ __('Tersedia di Tahap 3') }}"><i class="bi bi-camera-fill" aria-hidden="true"></i> {{ __('Upload Nota') }}</span>
    @endif
@endif

<x-ui.toasts />
<x-ui.confirm-modal />
@stack('scripts')
</body>
</html>
