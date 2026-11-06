<x-layouts.guest :title="__('Masuk')">
    <div class="pm-auth">
        {{-- Panel brand (desktop) --}}
        <aside class="pm-auth__aside" style="--pm-villa-image: url('{{ asset('images/villa-retreat.jpg') }}')">
            <div class="pm-auth__brand">
                <x-ui.brand-mark />
                <span>PropertyMe<small>VILLA MANAGEMENT</small></span>
            </div>
            <div class="pm-auth__story">
                <span class="pm-eyebrow"><span></span> {{ __('Ruang untuk berkembang') }}</span>
                <h2>{{ __('Villa istimewa.') }}<br><em>{{ __('Pengelolaan sempurna.') }}</em></h2>
                <p>{{ __('Keuangan semua villa Anda, rapi dalam satu tempat.') }} {{ __('Lebih sedikit kerumitan, lebih banyak ketenangan.') }}</p>
                <div class="pm-auth__features">
                    <div class="pm-auth__feature"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>{{ __('Reservasi terhubung') }}</span></div>
                    <div class="pm-auth__feature"><i class="bi bi-wallet2" aria-hidden="true"></i><span>{{ __('Keuangan terpantau') }}</span></div>
                    <div class="pm-auth__feature"><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i><span>{{ __('Laporan terperinci') }}</span></div>
                </div>
            </div>
            <div class="pm-auth__caption"><span>{{ __('Dibuat untuk hospitality yang lebih baik.') }}</span><i class="bi bi-flower2" aria-hidden="true"></i></div>
        </aside>

        <main class="pm-auth__main position-relative">
            {{-- Pilih bahasa sebelum login --}}
            <x-ui.locale-switcher class="position-absolute top-0 end-0 m-3" />
            <div class="pm-auth__form">
                <div class="d-flex align-items-center gap-2 mb-4 d-lg-none">
                    <x-ui.brand-mark />
                    <span class="pm-brand__name">PropertyMe<span class="pm-brand__sub">VILLA MANAGEMENT</span></span>
                </div>

                <div class="pm-auth__welcome"><i class="bi bi-flower2" aria-hidden="true"></i></div>
                <span class="pm-eyebrow text-muted">{{ __('Selamat datang kembali') }}</span>
                <h1 class="pm-auth__title">{{ __('Masuk ke akun Anda') }}</h1>
                <p class="text-muted mb-4">{{ __('Gunakan email atau username dan kata sandi yang diberikan Manager.') }}</p>

                <form method="POST" action="{{ route('login.store') }}" data-validate novalidate>
                    @csrf
                    <x-form.input name="email" type="text" :label="__('Email atau username')" required autofocus autocomplete="username"
                                  :placeholder="__('nama@email.com atau username')" autocapitalize="none" spellcheck="false" />

                    <div class="mb-3" data-field x-data="{ show: false }">
                        <label for="f_password" class="form-label">{{ __('Kata sandi') }}<span class="req" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <input :type="show ? 'text' : 'password'" type="password" name="password" id="f_password" required
                                   data-label="{{ __('Kata sandi') }}" autocomplete="current-password" placeholder="{{ __('Masukkan kata sandi Anda') }}"
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

                <p class="pm-auth__help fs-sm text-muted mt-4 mb-0">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                    {{ __('Lupa kata sandi? Hubungi Manager untuk mengatur ulang.') }}
                </p>
            </div>
            <div class="pm-auth__footer">© {{ date('Y') }} PropertyMe <span>{{ __('Villa Anda, dalam kendali.') }}</span></div>
        </main>
    </div>
</x-layouts.guest>
