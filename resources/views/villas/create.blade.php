<x-layouts.app :title="__('Tambah Villa')">
    <x-ui.page-header :title="__('Tambah Villa')" :subtitle="__('Lengkapi data villa. Kolom bertanda * wajib diisi.')" :back="route('villas.index')" :back-label="__('Daftar villa')" />
    @include('villas._form')
</x-layouts.app>
