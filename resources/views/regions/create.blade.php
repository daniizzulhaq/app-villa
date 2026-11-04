<x-layouts.app :title="__('Tambah Wilayah')">
    <x-ui.page-header :title="__('Tambah Wilayah')" :subtitle="__('Pilih villa & manager untuk wilayah ini.')" :back="route('regions.index')" :back-label="__('Wilayah')" />
    @include('regions._form')
</x-layouts.app>
