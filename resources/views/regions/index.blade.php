<x-layouts.app :title="__('Wilayah')">
    <x-ui.page-header :title="__('Wilayah')" :subtitle="__('Kelompokkan villa per wilayah, lalu tugaskan Manager. Manager hanya melihat & mengurus villa di wilayahnya; Master melihat semuanya.')">
        <x-slot:actions>
            <a href="{{ route('regions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Wilayah') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($regions->isEmpty())
        <x-ui.card>
            <x-ui.empty-state art="villa" :title="__('Belum ada wilayah')" :text="__('Buat wilayah pertama (mis. Ubud, Canggu), pilih villanya, lalu tugaskan Manager.')">
                <a href="{{ route('regions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Wilayah') }}</a>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="row g-3">
            @foreach ($regions as $r)
                <div class="col-md-6 col-xl-4">
                    <div class="pm-card h-100 d-flex flex-column">
                        <div class="p-3 border-bottom d-flex align-items-start gap-2">
                            <span class="pm-stat__icon flex-shrink-0" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <h2 class="h6 mb-0 text-truncate">{{ $r->name }}</h2>
                                @if ($r->description)<div class="fs-sm text-muted text-truncate">{{ $r->description }}</div>@endif
                            </div>
                            <a href="{{ route('regions.edit', $r) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit') }}</a>
                        </div>
                        <div class="p-3 flex-grow-1">
                            <div class="fs-xs text-muted text-uppercase fw-semibold mb-1">{{ __('Manager') }}</div>
                            @forelse ($r->managers as $m)
                                <x-ui.badge :variant="$m->is_active ? 'primary' : 'neutral'" plain class="me-1 mb-1">{{ $m->name }}</x-ui.badge>
                            @empty
                                <x-ui.badge variant="warning">{{ __('Belum ada manager — hanya Master yang mengurus') }}</x-ui.badge>
                            @endforelse

                            <div class="fs-xs text-muted text-uppercase fw-semibold mt-3 mb-1">{{ __(':count villa', ['count' => $r->villas->count()]) }}</div>
                            @if ($r->villas->isEmpty())
                                <span class="fs-sm text-muted">{{ __('Belum ada villa.') }}</span>
                            @else
                                <ul class="list-unstyled mb-0 fs-sm">
                                    @foreach ($r->villas as $v)
                                        <li class="text-truncate"><a href="{{ route('villas.show', $v) }}" class="text-decoration-none">{{ $v->name }}</a>
                                            @unless ($v->is_active)<span class="text-muted fs-xs">{{ __('(nonaktif)') }}</span>@endunless</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($unassigned->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2 mt-3" role="status">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>
                <strong>{{ __(':count villa belum punya wilayah', ['count' => $unassigned->count()]) }}</strong> —
                {{ __('hanya Master yang bisa mengaksesnya:') }} {{ $unassigned->pluck('name')->join(', ') }}.
            </div>
        </div>
    @endif
</x-layouts.app>
