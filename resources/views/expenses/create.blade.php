<x-layouts.app :title="__('Upload Nota')" :fab="false">
    <x-ui.page-header icon="bi-camera" :title="__('Upload Nota')" :subtitle="auth()->user()->isStaff() ? __('Foto nota, isi nominal & kategori, lalu kirim.') : __('Pengeluaran yang dicatat Manager langsung berstatus Valid.')" />
    @if ($villas->isEmpty())
        <x-ui.card><x-ui.empty-state art="villa" :title="__('Belum ada villa ditugaskan')" :text="__('Anda belum bisa mengunggah nota. Minta Manager menugaskan Anda ke villa.')" /></x-ui.card>
    @else
        @include('expenses._form')
    @endif
</x-layouts.app>
