<x-layouts.app :title="__('Edit Pemasukan')">
    <x-ui.page-header :title="__('Edit Pemasukan')" :subtitle="__('Perubahan langsung memperbarui semua total, grafik & laporan — tanpa data dobel.')" :back="route('incomes.show', $income)" :back-label="__('Detail pemasukan')" />
    @include('incomes._form')
</x-layouts.app>
