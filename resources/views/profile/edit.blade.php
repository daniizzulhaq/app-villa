<x-layouts.app :title="__('Profil')">
    @php
        $role = $user->roleEnum();
        $villaNames = match (true) {
            $user->isMaster() => null,
            $user->isRegionalManager() => \App\Models\Villa::accessibleBy($user)->orderBy('name')->pluck('name'),
            $user->isOwner() => $user->ownedVillas->pluck('name'),
            default => $user->assignedVillas->pluck('name'),
        };
    @endphp

    <x-ui.page-header :title="__('Profil saya')" :subtitle="__('Perbarui data diri dan kata sandi Anda.')" />

    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <x-ui.avatar :user="$user" size="lg" />
                    <div class="min-w-0">
                        <div class="fw-semibold text-ink fs-5 text-truncate">{{ $user->name }}</div>
                        @if ($role)<x-ui.badge :variant="$role->badge()" plain>{{ $role->shortLabel() }}</x-ui.badge>@endif
                    </div>
                </div>
                <dl class="pm-dl fs-sm mb-0">
                    <dt>{{ __('Akses villa') }}</dt>
                    <dd>
                        @if ($villaNames === null)
                            {{ __('Semua villa') }}
                        @elseif ($villaNames->isEmpty())
                            <span class="text-muted">{{ __('Belum ada — hubungi Manager') }}</span>
                        @else
                            {{ $villaNames->join(', ') }}
                        @endif
                    </dd>
                    <dt>{{ __('Login terakhir') }}</dt>
                    <dd>{{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-lg-8 d-grid gap-3">
            <x-ui.card :title="__('Data diri')">
                <form method="POST" action="{{ route('profile.update') }}" data-validate novalidate>
                    @csrf @method('PUT')
                    <x-form.input name="name" :label="__('Nama lengkap')" :value="$user->name" required maxlength="120" autocomplete="name" />
                    <div class="row">
                        <div class="col-sm-6"><x-form.input type="email" name="email" :label="$user->username ? __('Email (opsional)') : 'Email'" :value="$user->email" :required="! $user->username" autocomplete="email" inputmode="email" :help="$user->username ? __('Login: :username', ['username' => $user->username]) : null" /></div>
                        <div class="col-sm-6"><x-form.input type="tel" name="phone" :label="__('Nomor HP / WhatsApp')" :value="$user->phone" inputmode="tel" autocomplete="tel" /></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ __('Simpan Profil') }}</button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('Ganti kata sandi')" :subtitle="__('Minimal 8 karakter, berisi huruf dan angka.')">
                <form method="POST" action="{{ route('profile.password') }}" data-validate novalidate>
                    @csrf @method('PUT')
                    <x-form.input type="password" name="current_password" :label="__('Kata sandi saat ini')" required autocomplete="current-password" />
                    <div class="row">
                        <div class="col-sm-6"><x-form.input type="password" name="password" :label="__('Kata sandi baru')" required minlength="8" autocomplete="new-password" /></div>
                        <div class="col-sm-6"><x-form.input type="password" name="password_confirmation" :label="__('Ulangi kata sandi baru')" required minlength="8" autocomplete="new-password" /></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-key" aria-hidden="true"></i> {{ __('Ganti Kata Sandi') }}</button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
