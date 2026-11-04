<x-layouts.app :title="__('User & Akses')">
    <x-ui.page-header :title="__('User & Akses')" :subtitle="auth()->user()->isMaster() ? __('Kelola akun Manager, Owner, dan Staff serta villa yang bisa mereka akses.') : __('Kelola akun Staff yang bertugas di villa wilayah Anda.')">
        <x-slot:actions>
            <a href="{{ route('users.create', array_filter(['role' => $filters['role'] ?? null])) }}" class="btn btn-primary">
                <i class="bi bi-person-plus" aria-hidden="true"></i> {{ __('Tambah User') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        {{-- Tab per role --}}
        <nav class="pm-tabs" aria-label="{{ __('Filter role') }}">
            <a href="{{ route('users.index', array_filter(['q' => $filters['q'] ?? null, 'status' => $filters['status'] ?? null])) }}"
               @class(['active' => empty($filters['role'])]) @if(empty($filters['role'])) aria-current="page" @endif>
                {{ __('Semua') }} <span class="count">{{ $counts['all'] }}</span>
            </a>
            @foreach (\App\Enums\UserRole::cases() as $r)
                @continue(! auth()->user()->isMaster() && $r !== \App\Enums\UserRole::Staff)
                <a href="{{ route('users.index', array_filter(['role' => $r->value, 'q' => $filters['q'] ?? null, 'status' => $filters['status'] ?? null])) }}"
                   @class(['active' => ($filters['role'] ?? null) === $r->value]) @if(($filters['role'] ?? null) === $r->value) aria-current="page" @endif>
                    {{ $r->shortLabel() }} <span class="count">{{ $counts[$r->value] }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" class="pm-toolbar" role="search" data-no-lock>
            @if (! empty($filters['role']))<input type="hidden" name="role" value="{{ $filters['role'] }}">@endif
            <div class="pm-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="q" class="visually-hidden">{{ __('Cari user') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari nama, email, atau nomor HP…') }}">
            </div>
            <label for="status" class="visually-hidden">{{ __('Status') }}</label>
            <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Semua status') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Aktif') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Nonaktif') }}</option>
            </select>
            <button type="submit" class="btn btn-light"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
        </form>

        @if ($users->isEmpty())
            <x-ui.empty-state art="users" :title="__('Tidak ada user')" :text="__('Belum ada akun yang sesuai filter ini.')">
                <a href="{{ route('users.create', array_filter(['role' => $filters['role'] ?? null])) }}" class="btn btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> {{ __('Tambah User') }}</a>
            </x-ui.empty-state>
        @else
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead>
                    <tr><th>{{ __('Nama') }}</th><th>{{ __('Role') }}</th><th>{{ __('Akses villa') }}</th><th>{{ __('Login terakhir') }}</th><th>{{ __('Status') }}</th><th class="text-end"><span class="visually-hidden">{{ __('Aksi') }}</span></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $u)
                        @php
                            $role = $u->roleEnum();
                            $villaNames = match ($role) {
                                \App\Enums\UserRole::Master => null,
                                \App\Enums\UserRole::Manager => $u->regions->pluck('name')->map(fn ($n) => __('Wilayah :name', ['name' => $n])),
                                \App\Enums\UserRole::Owner => $u->ownedVillas->pluck('name'),
                                default => $u->assignedVillas->pluck('name'),
                            };
                        @endphp
                        <tr>
                            <td class="stack-full">
                                <div class="d-flex align-items-center gap-3">
                                    <x-ui.avatar :user="$u" />
                                    <div class="min-w-0">
                                        <div class="cell-title text-truncate">{{ $u->name }} @if($u->is(auth()->user()))<span class="fs-xs text-muted fw-normal">{{ __('(Anda)') }}</span>@endif</div>
                                        <div class="cell-sub text-truncate">{{ $u->email }}@if($u->phone) · {{ $u->phone }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="{{ __('Role') }}">
                                @if ($role)<x-ui.badge :variant="$role->badge()" plain>{{ $role->shortLabel() }}</x-ui.badge>@endif
                            </td>
                            <td data-label="{{ __('Akses villa') }}">
                                @if ($villaNames === null)
                                    <span class="fs-sm">{{ __('Semua villa') }}</span>
                                @elseif ($villaNames->isEmpty())
                                    <x-ui.badge variant="warning">{{ __('Belum ada villa') }}</x-ui.badge>
                                @else
                                    <span class="fs-sm">{{ $villaNames->take(2)->join(', ') }}@if($villaNames->count() > 2) +{{ $villaNames->count() - 2 }}@endif</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Login terakhir') }}" class="fs-sm">{{ $u->last_login_at?->diffForHumans() ?? __('Belum pernah') }}</td>
                            <td data-label="{{ __('Status') }}">
                                <x-ui.badge :variant="$u->is_active ? 'success' : 'neutral'">{{ $u->is_active ? __('Aktif') : __('Nonaktif') }}</x-ui.badge>
                            </td>
                            <td class="text-end stack-actions">
                                <div class="d-inline-flex gap-1 w-100 justify-content-end">
                                    @can('update', $u)
                                        <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light flex-grow-1 flex-md-grow-0"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('toggleActive', $u)
                                        <form method="POST" action="{{ route('users.toggle', $u) }}" class="flex-grow-1 flex-md-grow-0"
                                              data-confirm="{{ $u->is_active ? __('Nonaktifkan akun :name?', ['name' => $u->name]) : __('Aktifkan akun :name?', ['name' => $u->name]) }}"
                                              data-confirm-text="{{ $u->is_active ? __('Pengguna langsung keluar dan tidak bisa login. Data yang pernah diinput tetap tersimpan.') : __('Pengguna dapat login kembali.') }}"
                                              data-confirm-button="{{ $u->is_active ? __('Ya, nonaktifkan') : __('Ya, aktifkan') }}" @if($u->is_active) data-confirm-danger @endif>
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-light w-100">{{ $u->is_active ? __('Nonaktifkan') : __('Aktifkan') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fs-sm text-muted">{{ __('Menampilkan :firstitem–:lastitem dari :total user', ['firstitem' => $users->firstItem(), 'lastitem' => $users->lastItem(), 'total' => $users->total()]) }}</span>
                    {{ $users->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
</x-layouts.app>
