{{--
  Kalender reservasi bersama (Manager/Owner/Staff).
  Data dari route bookings.events — tanpa nominal uang. Klik booking → detail di bottom-sheet.
--}}
@props(['villas', 'platforms', 'selected' => null, 'initialDate' => null, 'showDetailLink' => false])
<div x-data="bookingCalendar({ eventsUrl: @js(route('bookings.events')), villa: @js($selected), initialDate: @js($initialDate) })" {{ $attributes }}>
    <div class="pm-toolbar border-0 px-0 pt-0">
        <label for="calVilla" class="visually-hidden">{{ __('Filter villa') }}</label>
        <select id="calVilla" class="form-select" x-model="villa" style="flex: 1 1 220px">
            <option value="">{{ __('Semua villa') }}</option>
            @foreach ($villas as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <label for="calDate" class="visually-hidden">{{ __('Lompat ke tanggal') }}</label>
        <input id="calDate" type="date" class="form-control" style="flex: 0 1 180px" @change="gotoDate($event.target.value)" aria-label="{{ __('Lompat ke tanggal') }}">
    </div>

    <div class="pm-legend mb-3" aria-label="{{ __('Keterangan warna') }}">
        @foreach ($platforms as $p)
            <span><span class="sw" style="background: {{ $p->color }}; border-color: {{ $p->color }}"></span>{{ $p->name }}</span>
        @endforeach
        <span><span class="sw" style="background: repeating-linear-gradient(135deg,#94a3b8 0 3px,#cbd5e1 3px 6px)"></span>{{ __('Perlu dilengkapi') }}</span>
        <span><span class="sw" style="background:#fff"></span>{{ __('Kosong') }}</span>
        <span x-show="villa" x-cloak class="text-muted">{{ __('· tanggal terisi berwarna latar') }}</span>
    </div>

    <div class="pm-calendar">
        <div class="pm-calendar__loading" x-show="loading" x-transition.opacity aria-live="polite">
            <div class="text-center"><span class="spinner-border text-primary" aria-hidden="true"></span><div class="fs-sm text-muted mt-2">{{ __('Memuat kalender…') }}</div></div>
        </div>
        <div x-ref="cal"></div>
    </div>

    {{-- Detail reservasi --}}
    <div x-cloak x-show="selected" class="pm-modal-backdrop" @click.self="selected = null" @keydown.escape.window="selected = null">
        <div class="pm-modal" role="dialog" aria-modal="true" aria-labelledby="bkTitle" x-trap.noscroll="selected">
            <template x-if="selected">
                <div>
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="label-caps" x-text="selected.platform + ' · ' + selected.villa"></div>
                            <h2 class="pm-modal__title mb-0" id="bkTitle" x-text="selected.guest || {{ Js::from(__('Nama tamu belum diisi')) }}"></h2>
                        </div>
                        <button type="button" class="btn-close" @click="selected = null" aria-label="{{ __('Tutup') }}"></button>
                    </div>
                    <span class="pm-badge mb-3" :class="'pm-badge--' + selected.statusBadge" x-text="selected.status"></span>
                    <dl class="pm-dl mb-3">
                        <dt>{{ __('Check-in') }}</dt><dd x-text="selected.checkIn + (selected.arrival ? ' · jam ' + selected.arrival : '')"></dd>
                        <dt>{{ __('Check-out') }}</dt><dd x-text="selected.checkOut"></dd>
                        <dt>{{ __('Lama menginap') }}</dt><dd x-text="selected.nights + ' malam'"></dd>
                        <dt>{{ __('Jumlah tamu') }}</dt><dd x-text="selected.guests ? selected.guests + ' orang' : '—'"></dd>
                        <template x-if="selected.ref"><dt>{{ __('Kode booking') }}</dt></template>
                        <template x-if="selected.ref"><dd x-text="selected.ref"></dd></template>
                        <dt>{{ __('Catatan') }}</dt><dd class="fw-normal" style="white-space:pre-line" x-text="selected.notes || '—'"></dd>
                    </dl>
                    <div class="pm-modal__actions">
                        <button type="button" class="btn btn-light" @click="selected = null">{{ __('Tutup') }}</button>
                        @if ($showDetailLink)
                            <a :href="selected.url" class="btn btn-primary" x-show="selected.url">{{ __('Lihat detail') }}</a>
                        @endif
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
