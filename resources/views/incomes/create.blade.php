<x-layouts.app :title="__('Tambah Pemasukan')">
    <x-ui.page-header icon="bi-wallet2" :title="__('Tambah Pemasukan')" :subtitle="__('Net revenue & management fee dihitung otomatis.')" :back="route('incomes.index')" :back-label="__('Pemasukan')" />
    @include('incomes._form')
</x-layouts.app>
