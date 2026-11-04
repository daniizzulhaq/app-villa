<x-layouts.app :title="__('Notifikasi')">
    <x-ui.page-header :title="__('Notifikasi')" :subtitle="__(':count belum dibaca', ['count' => auth()->user()->unreadNotifications()->count()])">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-light w-100" type="submit"><i class="bi bi-check2-all" aria-hidden="true"></i> {{ __('Tandai semua dibaca') }}</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        @forelse ($notifications as $n)
            @include('notifications._item', ['n' => $n])
        @empty
            <x-ui.empty-state :title="__('Belum ada notifikasi')" :text="__('Info nota, reservasi baru dari platform, dan laporan Final akan muncul di sini.')" />
        @endforelse
        @if ($notifications->hasPages())<div class="p-3">{{ $notifications->links() }}</div>@endif
    </x-ui.card>
</x-layouts.app>
