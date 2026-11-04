<x-layouts.app :title="__('Edit :name', ['name' => $region->name])">
    <x-ui.page-header :title="__('Edit :name', ['name' => $region->name])" :subtitle="__('Perubahan akses langsung berlaku untuk manager di wilayah ini.')" :back="route('regions.index')" :back-label="__('Wilayah')" />
    @include('regions._form')
</x-layouts.app>
