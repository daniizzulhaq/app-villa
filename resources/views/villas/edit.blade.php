<x-layouts.app :title="__('Edit :name', ['name' => $villa->name])">
    <x-ui.page-header :title="__('Edit :name', ['name' => $villa->name])" :subtitle="__('Perubahan fee tercatat di riwayat dan tidak mengubah pemasukan yang sudah lewat.')"
                      :back="route('villas.show', $villa)" :back-label="__('Detail villa')" />
    @include('villas._form')
</x-layouts.app>
