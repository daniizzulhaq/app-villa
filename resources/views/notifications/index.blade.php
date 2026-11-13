<x-layouts.app :title="$category->label()">
    <x-ui.page-header :title="$category->label()" :subtitle="__(':count belum dibaca', ['count' => $unreadCounts[$category->value]])">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <input type="hidden" name="category" value="{{ $category->value }}">
                <button class="btn btn-light w-100" type="submit"><i class="bi bi-check2-all" aria-hidden="true"></i> {{ __('Tandai semua dibaca') }}</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        <nav class="pm-tabs" aria-label="{{ __('Notification categories') }}">
            @foreach (\App\Enums\NotificationCategory::cases() as $inbox)
                <a href="{{ route('notifications.index', ['category' => $inbox->value]) }}" @class(['active' => $category === $inbox]) @if($category === $inbox) aria-current="page" @endif>
                    <i class="bi {{ $inbox === \App\Enums\NotificationCategory::Receipt ? 'bi-receipt' : 'bi-bell' }}" aria-hidden="true"></i>
                    {{ $inbox->label() }}
                    @if ($unreadCounts[$inbox->value])<span class="badge rounded-pill text-bg-light">{{ $unreadCounts[$inbox->value] }}</span>@endif
                </a>
            @endforeach
        </nav>
        <div data-notification-list="{{ $category->value }}">
        @forelse ($notifications as $n)
            @include('notifications._item', ['n' => $n])
        @empty
            <x-ui.empty-state :title="$category === \App\Enums\NotificationCategory::Receipt ? __('No receipt notifications yet.') : __('Belum ada notifikasi')"
                              :text="$category === \App\Enums\NotificationCategory::Receipt ? __('New receipts, resubmissions, and returned receipts appear here.') : __('Booking updates, schedules, and finalized reports appear here.')" />
        @endforelse
        </div>
        @if ($notifications->hasPages())<div class="p-3">{{ $notifications->links() }}</div>@endif
    </x-ui.card>
</x-layouts.app>
