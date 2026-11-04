<x-layouts.app :title="__('Status Sinkronisasi')">
    <x-ui.page-header :title="__('Status Sinkronisasi')" :subtitle="__('Reservasi Airbnb & Booking.com diambil otomatis setiap 30 menit lewat iCal.')">
        <x-slot:actions>
            <form method="POST" action="{{ route('sync.run') }}">
                @csrf
                <button type="submit" class="btn btn-primary w-100" data-loading-text="{{ __('Menyinkronkan…') }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Sinkron Sekarang') }}</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Link iCal terhubung')" :value="$summary['total']" icon="bi-link-45deg" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Berjalan normal')" :value="$summary['ok']" icon="bi-check-circle" icon-tone="success" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Bermasalah')" :value="$summary['error']" icon="bi-exclamation-triangle" :icon-tone="$summary['error'] ? 'danger' : 'success'" :tone="$summary['error'] ? 'negative' : null" /></div>
        <div class="col-6 col-lg-3"><x-ui.stat :label="__('Sinkron terakhir')" :value="$summary['last'] ? \Illuminate\Support\Carbon::parse($summary['last'])->diffForHumans() : '—'" icon="bi-clock-history" icon-tone="info" /></div>
    </div>

    <x-ui.card :title="__('Per villa')" flush>
        @if ($villas->isEmpty())
            <x-ui.empty-state art="villa" :title="__('Belum ada villa aktif')" />
        @else
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Villa') }}</th><th>{{ __('Platform') }}</th><th>{{ __('Sinkron terakhir') }}</th><th>{{ __('Status') }}</th><th>{{ __('Perlu dilengkapi') }}</th><th><span class="visually-hidden">{{ __('Aksi') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($villas as $v)
                        @forelse ($v->channels as $c)
                            <tr>
                                <td class="stack-full"><a href="{{ route('villas.show', $v) }}" class="cell-title text-decoration-none">{{ $v->name }}</a></td>
                                <td data-label="{{ __('Platform') }}"><span class="d-inline-flex align-items-center gap-1"><span class="pm-platform-dot" style="background: {{ $c->platform->color }}" aria-hidden="true"></span>{{ $c->platform->name }}</span></td>
                                <td data-label="{{ __('Sinkron terakhir') }}" class="fs-sm">
                                    {{ $c->last_synced_at?->diffForHumans() ?? __('Belum pernah') }}
                                    @if ($c->last_success_at && $c->last_error)<div class="cell-sub">{{ __('Berhasil terakhir :diffforhumans', ['diffforhumans' => $c->last_success_at->diffForHumans()]) }}</div>@endif
                                </td>
                                <td data-label="{{ __('Status') }}" class="stack-full">
                                    @if ($c->last_error)
                                        <x-ui.badge variant="danger">{{ __('Error') }}</x-ui.badge>
                                        <div class="fs-sm text-negative mt-1">{{ $c->last_error }}</div>
                                    @elseif ($c->last_success_at)
                                        <x-ui.badge variant="success">{{ __('Normal') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="neutral">{{ __('Menunggu sinkron pertama') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td data-label="{{ __('Perlu dilengkapi') }}">
                                    @if ($v->needs_completion_count)
                                        <a href="{{ route('bookings.index', ['villa' => $v->id, 'status' => 'needs_completion']) }}"><x-ui.badge variant="warning">{{ __(':needs_completion_count booking', ['needs_completion_count' => $v->needs_completion_count]) }}</x-ui.badge></a>
                                    @else <span class="text-muted fs-sm">—</span> @endif
                                </td>
                                <td class="text-end stack-actions">
                                    @if ($loop->first)
                                        <form method="POST" action="{{ route('sync.run') }}">
                                            @csrf <input type="hidden" name="villa" value="{{ $v->id }}">
                                            <button class="btn btn-sm btn-light w-100" type="submit" data-loading-text="{{ __('Sinkron…') }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Sinkron villa ini') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="stack-full"><a href="{{ route('villas.show', $v) }}" class="cell-title text-decoration-none">{{ $v->name }}</a></td>
                                <td colspan="4" class="stack-full text-muted fs-sm">{{ __('Belum ada link iCal — reservasi hanya dicatat manual.') }}</td>
                                <td class="text-end stack-actions"><a href="{{ route('villas.edit', $v) }}" class="btn btn-sm btn-light">{{ __('Tambah link') }}</a></td>
                            </tr>
                        @endforelse
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <div class="alert alert-info d-flex gap-2 mt-3 mb-0">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <div class="fs-sm">{{ __('iCal hanya berisi tanggal. Booking baru berstatus') }} <strong>{{ __('Perlu dilengkapi') }}</strong> {{ __('— isi nama tamu & catat pemasukan (manual atau import payout). Booking mendatang yang hilang dari kalender platform otomatis ditandai') }} <strong>{{ __('Cancelled') }}</strong>.</div>
    </div>
</x-layouts.app>
