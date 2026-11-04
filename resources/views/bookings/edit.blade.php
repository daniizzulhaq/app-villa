<x-layouts.app :title="__('Edit Reservasi')">
    <x-ui.page-header :title="$booking->status === \App\Enums\BookingStatus::NeedsCompletion ? __('Lengkapi Reservasi') : __('Edit Reservasi')"
                      :subtitle="$booking->villa->name.' · '.$booking->check_in->translatedFormat('d M').' – '.$booking->check_out->translatedFormat('d M Y')"
                      :back="route('bookings.show', $booking)" :back-label="__('Detail reservasi')" />
    @include('bookings._form')
</x-layouts.app>
