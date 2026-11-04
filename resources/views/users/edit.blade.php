<x-layouts.app :title="__('Edit :name', ['name' => $user->name])">
    <x-ui.page-header :title="__('Edit :name', ['name' => $user->name])" :subtitle="$user->email" :back="route('users.index')" :back-label="__('User & Akses')" />
    @include('users._form')
</x-layouts.app>
