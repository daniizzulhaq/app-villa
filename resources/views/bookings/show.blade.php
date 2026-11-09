<x-layouts.app :title="__('Reservasi').' '.($booking->guest_name ?: '#'.$booking->id)">
    <x-ui.page-header :title="$booking->guest_name ?: __('Nama tamu belum diisi')"
                      :subtitle="$booking->villa->name.' · '.$booking->platform->name" :back="route('bookings.index')" :back-label="__('Daftar reservasi')">
        <x-slot:actions>
            @can('update', $booking)
                @if (Route::has('incomes.create'))
                    <a href="{{ route('incomes.create', ['booking' => $booking->id]) }}" class="btn btn-light"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> {{ __('Tambah Pemasukan') }}</a>
                @endif
                <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> {{ $booking->status === \App\Enums\BookingStatus::NeedsCompletion ? __('Lengkapi Data') : __('Edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($booking->status === \App\Enums\BookingStatus::NeedsCompletion)
        <div class="alert alert-warning d-flex gap-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>{{ __('Reservasi ini masuk dari iCal dan hanya berisi tanggal. Lengkapi nama tamu & jumlah tamu, lalu catat pemasukannya.') }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <x-ui.card :title="__('Detail reservasi')">
                <div class="mb-3"><x-ui.badge :status="$booking->status" /></div>
                <dl class="pm-dl">
                    <dt>{{ __('Check-in') }}</dt><dd>{{ $booking->check_in->translatedFormat('l, d F Y') }}@if($booking->arrival_time) {{ __('· jam :arrival_time', ['arrival_time' => substr($booking->arrival_time, 0, 5)]) }}@endif</dd>
                    <dt>{{ __('Check-out') }}</dt><dd>{{ $booking->check_out->translatedFormat('l, d F Y') }}</dd>
                    <dt>{{ __('Lama menginap') }}</dt><dd>{{ __(':nights malam', ['nights' => $booking->nights()]) }}</dd>
                    <dt>{{ __('Jumlah tamu') }}</dt><dd>{{ $booking->guest_count ? $booking->guest_count.' orang' : '—' }}</dd>
                    @php $grossPaid = $booking->incomes->sum('gross_amount'); @endphp
                    <dt>{{ __('Harga reservasi') }}</dt>
                    <dd>
                        @if ($booking->total_price !== null)
                            <span class="num">@rupiah($booking->total_price)</span>
                            <span class="fs-xs text-muted">{{ $booking->price_source === 'ical' ? __('· dari iCal') : __('· diisi manual') }}</span>
                            @if ($booking->nights() > 0)<div class="fs-xs text-muted fw-normal">@rupiah($booking->total_price / $booking->nights()) {{ __('/ malam') }}</div>@endif
                        @elseif ($grossPaid > 0)
                            <span class="num">@rupiah($grossPaid)</span> <span class="fs-xs text-muted">{{ __('· dari pemasukan tercatat') }}</span>
                        @else
                            <span class="text-muted fw-normal">{{ __('Belum diisi') }}</span>
                            @if ($booking->isFromPlatformSync())<div class="fs-xs text-muted fw-normal">{{ __('iCal :name tidak menyertakan harga — isi lewat tombol Edit.', ['name' => $booking->platform->name]) }}</div>@endif
                        @endif
                    </dd>
                    <dt>{{ __('Kode booking') }}</dt><dd>{{ $booking->booking_ref ?: '—' }}</dd>
                    <dt>{{ __('Sumber data') }}</dt><dd>{{ match($booking->source) { 'ical' => __('Sinkron iCal otomatis'), 'import' => __('Import file'), default => __('Dicatat manual').($booking->creator ? ' '.__('oleh :name', ['name' => $booking->creator->name]) : '') } }}</dd>
                    @if ($booking->cancelled_at)<dt>{{ __('Dibatalkan') }}</dt><dd>{{ $booking->cancelled_at->translatedFormat('d M Y H:i') }}</dd>@endif
                    <dt>{{ __('Catatan') }}</dt><dd class="fw-normal" style="white-space:pre-line">{{ $booking->notes ?: '—' }}</dd>
                </dl>
            </x-ui.card>
        </div>
        <div class="col-lg-5">
            <x-ui.card :title="__('Pendapatan booking')" :subtitle="__(':count pembayaran tercatat', ['count' => $booking->incomes->count()])" flush>
                @forelse ($booking->incomes as $inc)
                    <div class="d-flex justify-content-between gap-2 px-4 py-3 border-bottom">
                        <div>
                            <div class="fw-semibold text-ink">{{ $inc->payment_date->translatedFormat('d M Y') }}</div>
                            <div class="fs-sm text-muted">{{ __('Gross') }} @rupiah($inc->gross_amount) {{ __('· Fee') }} @rupiah($inc->fee_amount)</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold num">@rupiah($inc->net_revenue)</div>
                            <div class="fs-xs text-muted">{{ __('net') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-muted fs-sm">{{ __('Belum ada pemasukan untuk reservasi ini.') }}</div>
                @endforelse
            </x-ui.card>

            @can('delete', $booking)
                <form method="POST" action="{{ route('bookings.destroy', $booking) }}" class="mt-3"
                      data-confirm="{{ __('Hapus reservasi ini?') }}" data-confirm-text="{{ __('Tanggal di kalender akan kosong kembali.') }}" data-confirm-button="{{ __('Ya, hapus') }}" data-confirm-danger>
                    @csrf @method('DELETE')
                    <button class="btn btn-danger-soft w-100" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus reservasi') }}</button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
