@php
    $isEdit = $booking->exists;
    $synced = $booking->isFromPlatformSync();
    // Booking iCal yang baru dilengkapi: sarankan Confirmed
    $statusValue = old('status', $booking->status === \App\Enums\BookingStatus::NeedsCompletion && $isEdit ? 'confirmed' : $booking->status?->value);
@endphp
<form method="POST" data-validate novalidate action="{{ $isEdit ? route('bookings.update', $booking) : route('bookings.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->has('check_in') && str_contains($errors->first('check_in'), 'bentrok'))
        <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-calendar-x" aria-hidden="true"></i><div>{{ $errors->first('check_in') }}</div></div>
    @elseif ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>{{ __('Ada :count isian yang perlu diperbaiki.', ['count' => $errors->count()]) }}</div></div>
    @endif

    <div class="pm-card">
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('1. Villa & tanggal') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Sistem menolak tanggal yang bentrok dengan reservasi lain di villa yang sama. Check-out & check-in di hari yang sama diperbolehkan.') }}</p>
                </div>
                <div class="col-lg-8">
                    @if ($synced)
                        <div class="alert alert-info d-flex gap-2"><i class="bi bi-lock" aria-hidden="true"></i>
                            <div class="fs-sm">{{ __('Villa, sumber, dan tanggal berasal dari kalender :name sehingga tidak bisa diubah di sini. Ubah di platform, PropertyMe ikut menyesuaikan saat sinkron berikutnya.', ['name' => $booking->platform->name]) }}</div></div>
                        <dl class="pm-dl mb-0">
                            <dt>{{ __('Villa') }}</dt><dd>{{ $booking->villa->name }}</dd>
                            <dt>{{ __('Sumber') }}</dt><dd>{{ $booking->platform->name }}</dd>
                            <dt>{{ __('Tanggal') }}</dt><dd>{{ __(':y – :y2 (:nights malam)', ['y' => $booking->check_in->translatedFormat('d M Y'), 'y2' => $booking->check_out->translatedFormat('d M Y'), 'nights' => $booking->nights()]) }}</dd>
                        </dl>
                    @else
                        <div class="row">
                            <div class="col-sm-6"><x-form.select name="villa_id" :label="__('Villa')" :options="$villas" :value="$booking->villa_id" :placeholder="__('Pilih villa')" required /></div>
                            <div class="col-sm-6"><x-form.select name="platform_id" :label="__('Sumber booking')" :options="$platforms" :value="$booking->platform_id" required /></div>
                        </div>
                        <div class="row">
                            <div class="col-6"><x-form.input type="date" name="check_in" :label="__('Check-in')" :value="$booking->check_in?->format('Y-m-d')" required /></div>
                            <div class="col-6"><x-form.input type="date" name="check_out" :label="__('Check-out')" :value="$booking->check_out?->format('Y-m-d')" required /></div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('2. Data tamu') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Nama, jumlah tamu, jam datang, dan catatan tampil di kalender Staff untuk persiapan villa.') }}</p>
                </div>
                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-sm-7"><x-form.input name="guest_name" :label="__('Nama tamu')" :value="$booking->guest_name" maxlength="150" autocomplete="off" /></div>
                        <div class="col-sm-5"><x-form.input name="booking_ref" :label="__('Kode booking')" :value="$booking->booking_ref" maxlength="100" :placeholder="__('mis. HMXXXXXXXX')" /></div>
                    </div>
                    <div class="row">
                        <div class="col-6"><x-form.input type="number" name="guest_count" :label="__('Jumlah tamu')" :value="$booking->guest_count" min="1" max="99" inputmode="numeric" /></div>
                        <div class="col-6"><x-form.input type="time" name="arrival_time" :label="__('Perkiraan jam datang')" :value="$booking->arrival_time ? substr($booking->arrival_time, 0, 5) : null" /></div>
                    </div>
                    <x-form.textarea name="notes" :label="__('Catatan untuk Staff')" :value="$booking->notes" rows="3" maxlength="3000" :placeholder="__('mis. minta extra bed, alergi, honeymoon decoration')" />
                    <x-form.select name="status" :label="__('Status')" :options="$statuses" :value="$statusValue" required class="mb-0" />
                </div>
            </div>
        </section>

        <x-form.actions :cancel="$isEdit ? route('bookings.show', $booking) : route('bookings.calendar')" :submit="$isEdit ? __('Simpan Perubahan') : __('Simpan Reservasi')" />
    </div>
</form>
