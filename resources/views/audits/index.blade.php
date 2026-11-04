@use('App\Http\Controllers\Manager\AuditController', 'A')
<x-layouts.app :title="__('Audit Log')">
    <x-ui.page-header :title="__('Audit Log')" :subtitle="__('Riwayat perubahan data penting: nilai lama → nilai baru, siapa, dan kapan.')" :back="route('settings.index')" :back-label="__('Pengaturan')" />

    <x-ui.card flush>
        <form method="GET" class="pm-toolbar" data-no-lock>
            <select name="type" class="form-select" aria-label="{{ __('Jenis data') }}" onchange="this.form.submit()" style="flex: 1 1 200px">
                <option value="">{{ __('Semua jenis data') }}</option>
                @foreach (A::TYPES as $cls => $l)<option value="{{ $cls }}" @selected(($filters['type'] ?? '') === $cls)>{{ __($l) }}</option>@endforeach
            </select>
            <select name="event" class="form-select" aria-label="{{ __('Aksi') }}" onchange="this.form.submit()">
                <option value="">{{ __('Semua aksi') }}</option>
                @foreach (A::EVENTS as $k => $l)<option value="{{ $k }}" @selected(($filters['event'] ?? '') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            <select name="user" class="form-select" aria-label="{{ __('Pengguna') }}" onchange="this.form.submit()">
                <option value="">{{ __('Semua pengguna') }}</option>
                @foreach ($users as $id => $n)<option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $n }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" style="flex:0 1 150px" aria-label="{{ __('Dari') }}">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" style="flex:0 1 150px" aria-label="{{ __('Sampai') }}">
            <button class="btn btn-light" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
        </form>

        @forelse ($audits as $a)
            @php
                $keys = collect(array_keys($a->old_values ?? []))->merge(array_keys($a->new_values ?? []))->unique()
                    ->reject(fn ($k) => in_array($k, ['id', 'created_at', 'updated_at', 'ical_token', 'password', 'remember_token'], true));
            @endphp
            <div class="px-4 py-3 border-bottom">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                    <x-ui.badge :variant="match($a->event) { 'created' => 'success', 'deleted' => 'danger', 'updated' => 'primary', default => 'neutral' }">{{ __(A::EVENTS[$a->event] ?? $a->event) }}</x-ui.badge>
                    <span class="fw-semibold text-ink">{{ __(A::TYPES[$a->auditable_type] ?? class_basename($a->auditable_type)) }} #{{ $a->auditable_id }}</span>
                    <span class="fs-sm text-muted">{{ __('oleh :otomatis · :i', ['otomatis' => $a->user?->name ?? __('Sistem / sinkron otomatis'), 'i' => $a->created_at->translatedFormat('d M Y H:i')]) }}</span>
                </div>
                @if ($keys->isNotEmpty())
                    <table class="table table-sm fs-sm mb-0" style="max-width: 760px">
                        @foreach ($keys->take(12) as $k)
                            <tr>
                                <td class="text-muted" style="width: 30%">{{ __(A::FIELDS[$k] ?? $k) }}</td>
                                @if ($a->event === 'updated')
                                    <td class="text-negative" style="text-decoration: line-through">{{ A::display($a->old_values[$k] ?? null) }}</td>
                                    <td aria-hidden="true">→</td>
                                @endif
                                <td class="{{ $a->event === 'updated' ? 'text-positive fw-semibold' : '' }}">{{ A::display($a->event === 'deleted' ? ($a->old_values[$k] ?? null) : ($a->new_values[$k] ?? null)) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @empty
            <x-ui.empty-state :title="__('Belum ada catatan audit')" :text="__('Perubahan pemasukan, pengeluaran, % fee, penghapusan & buka kunci bulan akan tercatat di sini.')" />
        @endforelse
        @if ($audits->hasPages())<div class="p-3">{{ $audits->links() }}</div>@endif
    </x-ui.card>
</x-layouts.app>
