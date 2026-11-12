<a href="{{ route('notifications.open', $n->id) }}" @class(['pm-notif', 'unread' => ! $n->read_at])>
    <span class="pm-notif__icon pm-notif__icon--{{ $n->data['tone'] ?? 'info' }}" aria-hidden="true"><i class="bi {{ $n->data['icon'] ?? 'bi-bell' }}"></i></span>
    <span class="min-w-0">
        <span class="d-block fw-semibold text-ink fs-sm">{{ $n->data['title'] ?? __('Notifikasi') }} @unless($n->read_at)<span class="visually-hidden">{{ __('(belum dibaca)') }}</span>@endunless</span>
        <span class="pm-notif__body fs-sm text-muted">{{ $n->data['body'] ?? '' }}</span>
        <span class="d-block fs-xs text-muted mt-1">{{ $n->created_at->diffForHumans() }}</span>
    </span>
</a>
