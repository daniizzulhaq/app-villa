<x-layouts.guest :title="__('Masuk')">
    <div class="pm-auth">
        {{-- Panel brand (desktop) --}}
        <aside class="pm-auth__aside" aria-hidden="true">
            <div class="d-flex align-items-center gap-2">
                <span class="pm-brand__mark">PM</span>
                <span class="fw-bold text-white fs-5">PropertyMe</span>
            </div>
            <div>
                <h2 class="mb-3">{{ __('Keuangan semua villa Anda, rapi dalam satu tempat.') }}</h2>
                <p class="mb-4">{{ __('Reservasi Airbnb & Booking.com, nota pengeluaran staff, management fee, hingga laporan laba bersih untuk owner.') }}</p>
                <div class="pm-auth__feature"><i class="bi bi-calendar-check"></i> {{ __('Kalender reservasi tersinkron otomatis') }}</div>
                <div class="pm-auth__feature"><i class="bi bi-camera"></i> {{ __('Upload nota langsung dari kamera HP') }}</div>
                <div class="pm-auth__feature"><i class="bi bi-file-earmark-bar-graph"></i> {{ __('Laporan PDF & Excel siap kirim ke owner') }}</div>
            </div>
            <div class="fs-sm" style="color:#8ea2c4">{{ __('© :y PropertyMe · Bali', ['y' => date('Y')]) }}</div>
        </aside>

        <main class="pm-auth__main position-relative">
            {{-- Pilih bahasa sebelum login --}}
            <x-ui.locale-switcher class="position-absolute top-0 end-0 m-3" />
            <div class="pm-auth__form">
                <div class="d-flex align-items-center gap-2 mb-4 d-lg-none">
                    <span class="pm-brand__mark" aria-hidden="true">PM</span>
                    <span class="pm-brand__name">PropertyMe<span class="pm-brand__sub">Villa Financial Management</span></span>
                </div>

                <h1 class="h3 mb-1">{{ __('Masuk ke akun Anda') }}</h1>
                <p class="text-muted mb-4">{{ __('Gunakan email atau username dan kata sandi yang diberikan Manager.') }}</p>

                <form method="POST" action="{{ route('login.store') }}" data-validate novalidate>
                    @csrf
                    <x-form.input name="email" type="text" :label="__('Email atau username')" required autofocus autocomplete="username"
                                  :placeholder="__('nama@email.com atau username')" autocapitalize="none" spellcheck="false" />

                    <div class="mb-3" data-field x-data="{ show: false }">
                        <label for="f_password" class="form-label">{{ __('Kata sandi') }}<span class="req" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <input :type="show ? 'text' : 'password'" type="password" name="password" id="f_password" required
                                   data-label="{{ __('Kata sandi') }}" autocomplete="current-password"
                                   @class(['form-control', 'is-invalid' => $errors->has('password')])>
                            <button type="button" class="btn btn-light border" @click="show = !show"
                                    :aria-label="show ? {{ Js::from(__('Sembunyikan kata sandi')) }} : {{ Js::from(__('Tampilkan kata sandi')) }}" aria-label="{{ __('Tampilkan kata sandi') }}">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" checked>
                        <label class="form-check-label" for="remember">{{ __('Tetap masuk di perangkat ini') }}</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="{{ __('Memeriksa…') }}">
                        {{ __('Masuk') }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <p class="fs-sm text-muted mt-4 mb-0">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    {{ __('Lupa kata sandi? Hubungi Manager untuk mengatur ulang.') }}
                </p>
            </div>
        </main>
    </div>
</x-layouts.guest>
