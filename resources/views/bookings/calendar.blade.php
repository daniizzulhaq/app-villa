<x-layouts.app :title="__('Kalender Reservasi')">
    <x-ui.page-header :title="__('Reservasi & Kalender')" :subtitle="__('Check-in & check-out semua platform dalam satu kalender.')">
        <x-slot:actions>
            @can('create', \App\Models\Booking::class)
                <a href="{{ route('sync.index') }}" class="btn btn-light"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Status Sinkron') }}</a>
                <a href="{{ route('bookings.create', array_filter(['villa' => $selectedVilla])) }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Reservasi') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('bookings._tabs')

    <x-ui.card>
        <x-booking-calendar :villas="$villas" :platforms="$platforms" :selected="$selectedVilla" show-detail-link />
    </x-ui.card>
</x-layouts.app>
