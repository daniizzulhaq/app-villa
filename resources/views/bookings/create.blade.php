<x-layouts.app :title="__('Tambah Reservasi')">
    <x-ui.page-header icon="bi-calendar2-plus" :title="__('Tambah Reservasi')" :subtitle="__('Untuk direct booking atau reservasi yang tidak masuk lewat iCal.')" :back="route('bookings.calendar')" :back-label="__('Kalender')" />
    @include('bookings._form')
</x-layouts.app>
