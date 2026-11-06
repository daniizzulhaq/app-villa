<x-layouts.app :title="__('Villa')">
    <x-ui.page-header :title="__('Koleksi villa')" :subtitle="__('Setiap properti istimewa, dikelola dengan perhatian yang sama.')">
        <x-slot:actions>
            @can('create', \App\Models\Villa::class)<a href="{{ route('villas.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Villa') }}</a>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="pm-collection-intro"><p>{{ __('Ruang istimewa. Potensi tak terbatas.') }}<small>{{ __('Kelola data villa, owner, management fee, link iCal, dan penugasan staff.') }}</small></p><i class="bi bi-houses" aria-hidden="true"></i></div>
    <div x-data="{ view: 'grid' }">
        {{-- Filter selalu terlihat di atas daftar --}}
        <form method="GET" class="pm-toolbar pm-collection-toolbar" role="search" data-no-lock>
            <div class="pm-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="q" class="visually-hidden">{{ __('Cari villa') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari nama atau lokasi villa…') }}">
            </div>
            <label for="status" class="visually-hidden">{{ __('Status') }}</label>
            <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Semua status') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Aktif') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Nonaktif') }}</option>
            </select>
            <label for="owner" class="visually-hidden">Owner</label>
            <select id="owner" name="owner" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Semua owner') }}</option>
                @foreach ($owners as $id => $name)
                    <option value="{{ $id }}" @selected((string) ($filters['owner'] ?? '') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-light"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('villas.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
            @endif
        </form>

        @if ($villas->isEmpty())
            @if (array_filter($filters))
                <x-ui.empty-state art="search" :title="__('Tidak ada villa yang cocok')" :text="__('Coba kata kunci lain atau reset filter.')">
                    <a href="{{ route('villas.index') }}" class="btn btn-light">{{ __('Reset filter') }}</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state art="villa" :title="__('Belum ada villa')" :text="__('Tambahkan villa pertama beserta owner, persentase management fee, dan staff yang bertugas.')">
                    @can('create', \App\Models\Villa::class)<a href="{{ route('villas.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Villa') }}</a>@endcan
                </x-ui.empty-state>
            @endif
        @else
            <div class="pm-section-heading">
                <div><h2>{{ __('Villa Anda') }} <span class="pm-badge pm-badge--neutral pm-badge--plain ms-1">{{ $villas->total() }}</span></h2><p>{{ __('Seluruh properti dalam satu pandangan.') }}</p></div>
                <div class="pm-view-toggle" role="group" aria-label="{{ __('Tampilan villa') }}">
                    <button type="button" @click="view = 'grid'" :class="{ active: view === 'grid' }" :aria-pressed="view === 'grid'" aria-label="{{ __('Tampilan kartu') }}"><i class="bi bi-grid" aria-hidden="true"></i></button>
                    <button type="button" @click="view = 'list'" :class="{ active: view === 'list' }" :aria-pressed="view === 'list'" aria-label="{{ __('Tampilan tabel') }}"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="pm-villa-grid pt-0" x-show="view === 'grid'">
                @foreach ($villas as $v)
                    <x-ui.villa-card :villa="$v" />
                @endforeach
            </div>
            <div class="pm-card table-responsive" x-show="view === 'list'" x-cloak>
                <table class="table pm-table pm-table--stack">
                    <thead>
                    <tr>
                        <th>{{ __('Villa') }}</th>
                        <th>Owner</th>
                        <th>{{ __('Management fee') }}</th>
                        <th>Staff</th>
                        <th>iCal</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end"><span class="visually-hidden">{{ __('Aksi') }}</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($villas as $v)
                        <tr>
                            <td class="stack-full">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($v->photoUrl())
                                        <img src="{{ $v->photoUrl() }}" alt="" class="pm-villa-thumb" loading="lazy">
                                    @else
                                        <span class="pm-villa-thumb" aria-hidden="true"><i class="bi bi-house"></i></span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('villas.show', $v) }}" class="cell-title text-decoration-none">{{ $v->name }}</a>
                                        <div class="cell-sub text-truncate">{{ $v->location ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Owner">
                                @if ($v->owner)
                                    {{ $v->owner->name }}
                                @else
                                    <span class="text-negative fs-sm">{{ __('Belum ada') }}</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Management fee') }}">
                                <span class="fw-semibold text-ink num">{{ \App\Support\Rupiah::percent($v->fee_percentage) }}</span>
                                <div class="cell-sub">{{ $v->fee_basis->label() }}</div>
                            </td>
                            <td data-label="Staff">
                                @if ($v->staff->isNotEmpty())
                                    <span class="fs-sm">{{ $v->staff->pluck('name')->take(2)->join(', ') }}@if($v->staff->count() > 2) +{{ $v->staff->count() - 2 }}@endif</span>
                                @else
                                    <x-ui.badge variant="warning">{{ __('Belum ada') }}</x-ui.badge>
                                @endif
                            </td>
                            <td data-label="iCal">
                                <div class="d-flex gap-2 align-items-center">
                                    @forelse ($v->channels as $c)
                                        <span class="d-inline-flex align-items-center gap-1 fs-sm" title="{{ $c->platform->name }}">
                                            <span class="pm-platform-dot" style="background: {{ $c->platform->color }}" aria-hidden="true"></span>{{ $c->platform->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted fs-sm">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td data-label="{{ __('Status') }}">
                                <x-ui.badge :variant="$v->is_active ? 'success' : 'neutral'">{{ $v->is_active ? __('Aktif') : __('Nonaktif') }}</x-ui.badge>
                            </td>
                            <td class="text-end stack-actions">
                                <a href="{{ route('villas.show', $v) }}" class="btn btn-sm btn-light">{{ __('Detail') }}</a>
                                <a href="{{ route('villas.edit', $v) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($villas->hasPages())
                <div class="pm-pagination">
                    <span class="fs-sm text-muted">{{ __('Menampilkan :firstitem–:lastitem dari :total villa', ['firstitem' => $villas->firstItem(), 'lastitem' => $villas->lastItem(), 'total' => $villas->total()]) }}</span>
                    {{ $villas->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
