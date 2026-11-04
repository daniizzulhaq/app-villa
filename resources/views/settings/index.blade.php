<x-layouts.app :title="__('Pengaturan')">
    <x-ui.page-header :title="__('Pengaturan')" :subtitle="__('Kategori pengeluaran dan informasi sistem.')" />

    <div class="row g-3">
        <div class="col-xl-8">
            <x-ui.card :title="__('Kategori pengeluaran')" :subtitle="__('16 kategori bawaan + kategori custom. Kategori nonaktif tidak muncul di form Staff.')" flush>
                <form method="POST" action="{{ route('settings.categories.store') }}" class="pm-toolbar" data-validate novalidate>
                    @csrf
                    <div class="flex-grow-1" data-field>
                        <label for="newcat" class="visually-hidden">{{ __('Nama kategori baru') }}</label>
                        <input id="newcat" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('Nama kategori baru, mis. Pest Control') }}" required maxlength="100" data-label="{{ __('Nama kategori') }}" value="{{ old('name') }}">
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <select name="icon" class="form-select" aria-label="{{ __('Ikon') }}" style="flex: 0 1 160px">
                        @foreach ($icons as $i)<option value="{{ $i }}">{{ str_replace('bi-', '', $i) }}</option>@endforeach
                    </select>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah') }}</button>
                </form>
                <div class="table-responsive">
                    <table class="table pm-table pm-table--stack">
                        <thead><tr><th>{{ __('Kategori') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Dipakai') }}</th><th>{{ __('Status') }}</th><th><span class="visually-hidden">{{ __('Aksi') }}</span></th></tr></thead>
                        <tbody>
                        @foreach ($categories as $c)
                            <tr>
                                <td class="stack-full"><i class="bi {{ $c->icon }} text-muted me-1" aria-hidden="true"></i> <span class="cell-title">{{ __($c->name) }}</span></td>
                                <td data-label="{{ __('Jenis') }}">{{ $c->is_default ? __('Bawaan') : __('Custom') }}</td>
                                <td data-label="{{ __('Dipakai') }}">{{ __(':expenses_count transaksi', ['expenses_count' => $c->expenses_count]) }}</td>
                                <td data-label="{{ __('Status') }}"><x-ui.badge :variant="$c->is_active ? 'success' : 'neutral'">{{ $c->is_active ? __('Aktif') : __('Nonaktif') }}</x-ui.badge></td>
                                <td class="text-end stack-actions">
                                    <form method="POST" action="{{ route('settings.categories.update', $c) }}" class="d-inline">
                                        @csrf @method('PATCH') <input type="hidden" name="toggle" value="1">
                                        <button class="btn btn-sm btn-light" type="submit">{{ $c->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</button>
                                    </form>
                                    @if (! $c->is_default && $c->expenses_count === 0)
                                        <form method="POST" action="{{ route('settings.categories.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Hapus kategori :name?', ['name' => __($c->name)]) }}" data-confirm-button="{{ __('Ya, hapus') }}" data-confirm-danger>
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger-soft" type="submit" aria-label="{{ __('Hapus :name', ['name' => __($c->name)]) }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
        <div class="col-xl-4 d-grid gap-3 align-content-start">
            <x-ui.card :title="__('Sistem')">
                <dl class="pm-dl fs-sm mb-0">
                    <dt>{{ __('Zona waktu') }}</dt><dd>{{ config('app.timezone') }}</dd>
                    <dt>{{ __('Notifikasi email') }}</dt><dd>{{ config('propertyme.notify_by_email') ? __('Aktif (').config('mail.default').')' : __('Nonaktif') }}</dd>
                    <dt>{{ __('Pengingat Staff') }}</dt><dd>{{ __('Setiap hari jam :tomorrow_reminder_at', ['tomorrow_reminder_at' => config('propertyme.tomorrow_reminder_at')]) }}</dd>
                    <dt>{{ __('Sinkron iCal') }}</dt><dd>{{ __('Setiap 30 menit') }}</dd>
                    <dt>{{ __('Versi') }}</dt><dd>{{ __('Laravel :version · PHP :php_version', ['version' => app()->version(), 'php_version' => PHP_VERSION]) }}</dd>
                </dl>
            </x-ui.card>
            <x-ui.card :title="__('Lainnya')">
                <div class="d-grid gap-2">
                    <a href="{{ route('audits.index') }}" class="btn btn-light justify-content-start"><i class="bi bi-journal-text" aria-hidden="true"></i> {{ __('Audit log') }}</a>
                    <a href="{{ route('imports.create') }}" class="btn btn-light justify-content-start"><i class="bi bi-upload" aria-hidden="true"></i> {{ __('Import payout CSV/Excel') }}</a>
                    <a href="{{ route('sync.index') }}" class="btn btn-light justify-content-start"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Status sinkronisasi') }}</a>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
