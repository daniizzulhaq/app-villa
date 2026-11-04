<x-layouts.app :title="__('Riwayat Aktivitas')">
    <x-ui.page-header :title="__('Riwayat Aktivitas')" :subtitle="__('Nota yang Anda unggah. Total bulan ini: :total', ['total' => \App\Support\Rupiah::format($monthTotal)])">
        <x-slot:actions>
            <a href="{{ route('expenses.create') }}" class="btn btn-primary d-none d-md-inline-flex"><i class="bi bi-camera" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        <nav class="pm-tabs" aria-label="{{ __('Filter status') }}">
            <a href="{{ route('staff.activity') }}" @class(['active' => ! $status])>{{ __('Semua') }} <span class="count">{{ $counts->sum() }}</span></a>
            @foreach (\App\Enums\ExpenseStatus::cases() as $s)
                <a href="{{ route('staff.activity', ['status' => $s->value]) }}" @class(['active' => $status === $s->value])>{{ $s->label() }} <span class="count">{{ $counts[$s->value] ?? 0 }}</span></a>
            @endforeach
        </nav>

        @forelse ($expenses as $e)
            <a href="{{ route('expenses.show', $e) }}" class="d-flex gap-3 px-4 py-3 border-bottom text-decoration-none {{ $e->status === \App\Enums\ExpenseStatus::Returned ? 'bg-danger-subtle' : '' }}" style="{{ $e->status === \App\Enums\ExpenseStatus::Returned ? 'background: var(--pm-danger-soft) !important' : '' }}">
                <span class="pm-agenda__icon" style="background: var(--pm-surface-2); color: var(--pm-ink-500)" aria-hidden="true"><i class="bi {{ $e->category->icon }}"></i></span>
                <span class="min-w-0 flex-grow-1">
                    <span class="d-flex justify-content-between gap-2">
                        <span class="fw-semibold text-ink text-truncate">{{ $e->description }}</span>
                        <span class="fw-semibold text-ink num flex-shrink-0">@rupiah($e->amount)</span>
                    </span>
                    <span class="d-block fs-sm text-muted">{{ __(':name · :y · :count foto', ['name' => $e->villa?->name, 'y' => $e->expense_date->translatedFormat('d M Y'), 'count' => $e->receipts->count()]) }}</span>
                    <span class="d-flex align-items-center gap-2 mt-1">
                        <x-ui.badge :status="$e->status" />
                        @if ($e->checked_at)<span class="fs-xs text-muted">{{ __('oleh :name :diffforhumans', ['name' => $e->checker?->name, 'diffforhumans' => $e->checked_at->diffForHumans()]) }}</span>@endif
                    </span>
                    @if ($e->status === \App\Enums\ExpenseStatus::Returned)
                        <span class="d-block fs-sm text-negative mt-1"><i class="bi bi-chat-left-quote" aria-hidden="true"></i> {{ $e->return_reason }}</span>
                        <span class="btn btn-sm btn-danger mt-2">{{ __('Perbaiki & kirim ulang') }}</span>
                    @endif
                </span>
            </a>
        @empty
            <x-ui.empty-state :title="__('Belum ada nota')" :text="__('Nota yang Anda unggah dan status pemeriksaannya akan tampil di sini.')">
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-camera" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
            </x-ui.empty-state>
        @endforelse
        @if ($expenses->hasPages())<div class="p-3">{{ $expenses->links() }}</div>@endif
    </x-ui.card>
</x-layouts.app>
