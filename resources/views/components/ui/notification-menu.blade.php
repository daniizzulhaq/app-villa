@props(['category'])
@php
    $notifications = $category->notifications(auth()->user());
    $unread = (clone $notifications)->whereNull('read_at')->count();
    $latest = $notifications->limit(6)->get();
    $label = $category->label();
    $isReceipt = $category === \App\Enums\NotificationCategory::Receipt;
    $menuId = 'pm-notifications-'.$category->value;
@endphp
<div class="dropdown" data-notification-category="{{ $category->value }}">
    <button type="button" class="btn btn-ghost btn-icon position-relative" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside"
            aria-expanded="false" aria-controls="{{ $menuId }}" title="{{ $label }}"
            aria-label="{{ $label }}{{ $unread ? ', '.__(':count belum dibaca', ['count' => $unread]) : '' }}">
        <i class="bi {{ $isReceipt ? 'bi-receipt' : ($unread ? 'bi-bell-fill' : 'bi-bell') }}" aria-hidden="true"></i>
        @if ($unread)<span class="pm-notif-dot" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </button>
    <div id="{{ $menuId }}" class="dropdown-menu dropdown-menu-end pm-notif-menu">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong class="text-ink">{{ $label }}</strong>
            @if ($unread)
                <form method="POST" action="{{ route('notifications.read-all') }}" data-no-lock>
                    @csrf
                    <input type="hidden" name="category" value="{{ $category->value }}">
                    <button class="btn btn-sm btn-ghost" type="submit">{{ __('Tandai dibaca') }}</button>
                </form>
            @endif
        </div>
        <div class="pm-notif-menu__items" role="region" aria-label="{{ $label }}" tabindex="0">
            @forelse ($latest as $n)
                @include('notifications._item', ['n' => $n])
            @empty
                <p class="px-3 py-4 text-muted fs-sm mb-0 text-center">{{ $isReceipt ? __('No receipt notifications yet.') : __('Belum ada notifikasi.') }}</p>
            @endforelse
        </div>
        <a href="{{ route('notifications.index', ['category' => $category->value]) }}" class="d-block text-center py-2 fs-sm fw-semibold text-decoration-none">{{ __('Lihat semua') }}</a>
    </div>
</div>
