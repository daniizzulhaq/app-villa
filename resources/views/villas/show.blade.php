<x-layouts.app :title="$villa->name">
    <x-ui.page-header :title="$villa->name" :subtitle="$villa->location" :back="route('villas.index')" :back-label="__('Daftar villa')">
        <x-slot:actions>
            @can('delete', $villa)
            <form method="POST" action="{{ route('villas.toggle', $villa) }}"
                  data-confirm="{{ $villa->is_active ? __('Nonaktifkan villa ini?') : __('Aktifkan kembali villa ini?') }}"
                  data-confirm-text="{{ $villa->is_active ? __('Villa tidak akan muncul di pilihan Staff. Data reservasi & transaksi tetap tersimpan.') : __('Villa akan muncul kembali di kalender dan pilihan Staff.') }}"
                  data-confirm-button="{{ $villa->is_active ? __('Ya, nonaktifkan') : __('Ya, aktifkan') }}"
                  @if($villa->is_active) data-confirm-danger @endif>
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-light w-100">
                    <i class="bi {{ $villa->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}" aria-hidden="true"></i>
                    {{ $villa->is_active ? __('Nonaktifkan') : __('Aktifkan') }}
                </button>
            </form>
            @endcan
            <a href="{{ route('villas.edit', $villa) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit Villa') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3">
        <div class="col-lg-8 d-grid gap-3 align-content-start">
            {{-- Ringkasan --}}
            <x-ui.card>
                <div class="row g-4">
                    <div class="col-md-5">
                        @if ($villa->photoUrl())
                            <img src="{{ $villa->photoUrl() }}" alt="{{ __('Foto :name', ['name' => $villa->name]) }}" class="pm-villa-cover">
                        @else
                            <div class="pm-villa-cover" aria-hidden="true"><i class="bi bi-house"></i></div>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <div class="mb-3">
                            <x-ui.badge :variant="$villa->is_active ? 'success' : 'neutral'">{{ $villa->is_active ? __('Aktif') : __('Nonaktif') }}</x-ui.badge>
                        </div>
                        <dl class="pm-dl">
                            <dt>{{ __('Wilayah') }}</dt>
                            <dd>
                                @if ($villa->region)
                                    {{ $villa->region->name }}
                                    <div class="fs-sm text-muted fw-normal">{{ __('Manager') }}: {{ $villa->region->managers->pluck('name')->join(', ') ?: '—' }}</div>
                                @else
                                    <span class="text-muted">{{ __('Tanpa wilayah (hanya Master)') }}</span>
                                @endif
                            </dd>
                            <dt>{{ __('Owner') }}</dt>
                            <dd>
                                @if ($villa->owner)
                                    {{ $villa->owner->name }}
                                    <div class="fs-sm text-muted fw-normal">{{ $villa->owner->email }}</div>
                                @else
                                    <span class="text-negative">{{ __('Belum ditentukan') }}</span>
                                @endif
                            </dd>
                            <dt>{{ __('Mulai dikelola') }}</dt>
                            <dd>{{ $villa->started_at?->translatedFormat('d F Y') ?? '—' }}</dd>
                            <dt>{{ __('Zona waktu') }}</dt>
                            <dd>{{ $villa->timezone }}</dd>
                            @if ($villa->notes)
                                <dt>{{ __('Catatan') }}</dt>
                                <dd class="fw-normal" style="white-space: pre-line">{{ $villa->notes }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </x-ui.card>

            {{-- Fee & riwayat --}}
            <x-ui.card :title="__('Management fee')" :subtitle="__('Snapshot fee disimpan di setiap pemasukan, jadi perubahan tidak mengubah bulan yang sudah lewat.')">
                <div class="d-flex flex-wrap gap-4 align-items-end mb-4">
                    <div>
                        <div class="pm-stat__label">{{ __('Fee saat ini') }}</div>
                        <div class="pm-stat__value" style="font-size: var(--pm-fs-2xl)">{{ \App\Support\Rupiah::percent($villa->fee_percentage) }}</div>
                    </div>
                    <div>
                        <div class="pm-stat__label">{{ __('Basis fee') }}</div>
                        <div class="fw-semibold text-ink">{{ $villa->fee_basis->label() }}</div>
                        @if ($villa->fee_basis_note)<div class="fs-sm text-muted">{{ $villa->fee_basis_note }}</div>@endif
                    </div>
                </div>

                <h3 class="label-caps mb-3">{{ __('Riwayat perubahan fee') }}</h3>
                <ul class="pm-timeline">
                    @foreach ($villa->feeHistories as $h)
                        <li @class(['is-current' => $loop->first])>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span class="fw-semibold text-ink">{{ \App\Support\Rupiah::percent($h->fee_percentage) }}</span>
                                <span class="text-muted">·</span>
                                <span>{{ $h->fee_basis->label() }}</span>
                                @if ($loop->first)<x-ui.badge variant="primary">{{ __('Berlaku') }}</x-ui.badge>@endif
                            </div>
                            <div class="fs-sm text-muted">
                                {{ __('Berlaku mulai :y · diubah oleh :sistem, :i', ['y' => $h->effective_from->translatedFormat('d F Y'), 'sistem' => $h->changer?->name ?? __('Sistem'), 'i' => $h->created_at->translatedFormat('d M Y H:i')]) }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>

        <div class="col-lg-4 d-grid gap-3 align-content-start">
            {{-- Staff --}}
            <x-ui.card :title="__('Staff bertugas')" :subtitle="$villa->staff->count().' orang'">
                <x-slot:actions>
                    <a href="{{ route('villas.edit', $villa) }}#staff" class="btn btn-sm btn-light">{{ __('Atur') }}</a>
                </x-slot:actions>
                @forelse ($villa->staff as $s)
                    <div class="d-flex align-items-center gap-2 py-2 @unless($loop->last) border-bottom @endunless">
                        <x-ui.avatar :user="$s" />
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-ink text-truncate">{{ $s->name }}</div>
                            <div class="fs-sm text-muted">{{ $s->phone ?: $s->email }}</div>
                        </div>
                        @if ($s->phone)
                            <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/\D/', '', $s->phone)) }}" target="_blank" rel="noopener"
                               class="btn btn-sm btn-ghost btn-icon" aria-label="WhatsApp {{ $s->name }}" data-bs-toggle="tooltip" title="WhatsApp">
                                <i class="bi bi-whatsapp" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0 fs-sm">{{ __('Belum ada staff. Staff yang ditugaskan dapat melihat kalender dan mengunggah nota villa ini.') }}</p>
                @endforelse
            </x-ui.card>

            {{-- iCal --}}
            <x-ui.card :title="__('Sinkron iCal')">
                @forelse ($villa->channels as $c)
                    <div class="py-2 @unless($loop->last) border-bottom @endunless">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="pm-platform-dot" style="background: {{ $c->platform->color }}" aria-hidden="true"></span>
                            <span class="fw-semibold text-ink">{{ $c->platform->name }}</span>
                            @if ($c->last_error)
                                <x-ui.badge variant="danger" class="ms-auto">{{ __('Error') }}</x-ui.badge>
                            @elseif ($c->last_success_at)
                                <x-ui.badge variant="success" class="ms-auto">{{ __('Tersinkron') }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral" class="ms-auto">{{ __('Belum sinkron') }}</x-ui.badge>
                            @endif
                        </div>
                        <div class="fs-sm text-muted text-truncate" title="{{ $c->ical_url }}">{{ $c->ical_url }}</div>
                        <div class="fs-xs text-muted">{{ __('Terakhir: :diffforhumans', ['diffforhumans' => $c->last_synced_at?->diffForHumans() ?? '—']) }}</div>
                    </div>
                @empty
                    <p class="text-muted fs-sm mb-2">{{ __('Belum ada link iCal. Reservasi bisa dicatat manual atau tambahkan link agar masuk otomatis.') }}</p>
                    <a href="{{ route('villas.edit', $villa) }}" class="btn btn-sm btn-light">{{ __('Tambah link iCal') }}</a>
                @endforelse
                @if ($villa->channels->isNotEmpty())
                    <form method="POST" action="{{ route('sync.run') }}" class="mt-3">
                        @csrf <input type="hidden" name="villa" value="{{ $villa->id }}">
                        <button class="btn btn-sm btn-light w-100" type="submit" data-loading-text="{{ __('Menyinkronkan…') }}"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Sinkron sekarang') }}</button>
                    </form>
                @endif
            </x-ui.card>

            {{-- Ekspor iCal: pasang di Airbnb/Booking.com agar direct booking ikut memblokir tanggal --}}
            <x-ui.card :title="__('Link ekspor iCal')" :subtitle="__('Tempel di platform agar tanggal Direct Booking ikut terblokir.')">
                @foreach ([['airbnb', __('Untuk Airbnb')], ['booking-com', __('Untuk Booking.com')], [null, __('Semua reservasi (Google Calendar, dll.)')]] as [$slug, $label])
                    @php $url = route('ical.export', ['token' => $villa->ical_token] + ($slug ? ['exclude' => $slug] : [])); @endphp
                    <div class="mb-3" x-data="copyText">
                        <label class="form-label fs-sm" for="ical_{{ $slug ?? 'all' }}">{{ $label }}</label>
                        <div class="pm-copy">
                            <input id="ical_{{ $slug ?? 'all' }}" class="form-control form-control-sm" value="{{ $url }}" readonly @focus="$el.select()">
                            <button type="button" class="btn btn-sm btn-light flex-shrink-0" @click="copy(@js($url))" :aria-label="copied ? {{ Js::from(__('Tersalin')) }} : {{ Js::from(__('Salin link')) }}">
                                <i class="bi" :class="copied ? 'bi-check2' : 'bi-copy'" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
                <p class="fs-xs text-muted mb-0"><i class="bi bi-shield-lock" aria-hidden="true"></i> {{ __('Link berisi kode rahasia dan hanya memuat tanggal terisi — tanpa nama tamu atau nominal.') }}</p>
            </x-ui.card>

            {{-- Hapus (khusus Master) --}}
            @can('delete', $villa)
            <form method="POST" action="{{ route('villas.destroy', $villa) }}"
                  data-confirm="{{ __('Hapus villa :name?', ['name' => $villa->name]) }}"
                  data-confirm-text="{{ __('Hanya villa tanpa reservasi & transaksi yang dapat dihapus. Untuk villa lain, gunakan Nonaktifkan.') }}"
                  data-confirm-button="{{ __('Ya, hapus') }}" data-confirm-danger>
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger-soft w-100"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus villa') }}</button>
            </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
