<x-layouts.app :title="__('Tambah User')">
    <x-ui.page-header :title="__('Tambah User')" :subtitle="__('Buat akun lalu bagikan email & kata sandi ke pengguna.')" :back="route('users.index')" :back-label="__('User & Akses')" />
    @include('users._form')
</x-layouts.app>
