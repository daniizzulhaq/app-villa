{{--
  Form user: pilih role dulu (kartu besar dengan penjelasan), lalu bagian akses villa menyesuaikan role:
  Staff → villa yang ditugaskan · Owner → villa yang dimiliki · Manager → wilayah · Master → semua villa
--}}
@php
    $isEdit = $user->exists;
    $currentRole = old('role', $role->value);
    $isSelf = $isEdit && $user->is(auth()->user());
@endphp

<form method="POST" data-validate novalidate action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}"
      x-data="{ role: @js($currentRole) }">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
            <div>{{ __('Ada :count isian yang perlu diperbaiki.', ['count' => $errors->count()]) }}</div>
        </div>
    @endif

    <div class="pm-card">
        {{-- 1. Role --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('1. Role') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Role menentukan menu dan data yang bisa dilihat pengguna.') }}</p>
                </div>
                <div class="col-lg-8">
                    <fieldset>
                        <legend class="visually-hidden">{{ __('Pilih role') }}</legend>
                        <div class="row g-2">
                            @foreach ($roles as $r)
                                <div class="col-md-6 col-xl-3">
                                    <label class="pm-choice">
                                        <input type="radio" name="role" value="{{ $r->value }}" class="form-check-input" x-model="role" required
                                               @checked($currentRole === $r->value) @disabled($isSelf && $r->value !== $currentRole)>
                                        <span>
                                            <span class="pm-choice__title d-block">{{ $r->shortLabel() }}</span>
                                            <span class="pm-choice__desc d-block">{{ $r->description() }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @if ($isSelf)<div class="form-text">{{ __('Anda tidak dapat mengubah role akun sendiri.') }}</div>@endif
                        @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>
                </div>
            </div>
        </section>

        {{-- 2. Data akun --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('2. Data akun') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Login bisa memakai username atau email. Email opsional — hanya dipakai untuk menerima notifikasi lewat email.') }}</p>
                </div>
                <div class="col-lg-8">
                    <x-form.input name="name" :label="__('Nama lengkap')" :value="$user->name" required maxlength="120" autocomplete="off" />
                    <x-form.input name="username" :label="__('Username')" :value="$user->username" maxlength="50" autocomplete="off"
                                  autocapitalize="none" spellcheck="false" :placeholder="__('mis. made')" :help="__('Dipakai untuk login. Huruf kecil, tanpa spasi. Wajib jika email dikosongkan.')" />
                    <div class="row">
                        <div class="col-sm-6">
                            <x-form.input type="email" name="email" :label="__('Email (opsional)')" :value="$user->email" maxlength="190" inputmode="email" autocomplete="off" />
                        </div>
                        <div class="col-sm-6">
                            <x-form.input type="tel" name="phone" :label="__('Nomor HP / WhatsApp')" :value="$user->phone" maxlength="30" inputmode="tel" :placeholder="__('08xx xxxx xxxx')" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <x-form.input type="password" name="password" :label="$isEdit ? __('Kata sandi baru') : __('Kata sandi')" :required="! $isEdit"
                                          minlength="8" autocomplete="new-password"
                                          :help="$isEdit ? __('Kosongkan jika tidak ingin mengganti.') : __('Minimal 8 karakter, berisi huruf dan angka.')" />
                        </div>
                        <div class="col-sm-6">
                            <x-form.input type="password" name="password_confirmation" :label="__('Ulangi kata sandi')" :required="! $isEdit" minlength="8" autocomplete="new-password" />
                        </div>
                    </div>
                    @if ($isSelf)
                        <input type="hidden" name="is_active" value="1">
                    @else
                        <x-form.switch name="is_active" :label="__('Akun aktif')" :checked="$user->is_active ?? true"
                                       :help="__('Akun nonaktif tidak bisa login. Data yang pernah diinput tetap tersimpan.')" class="mb-0" />
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Wilayah (Manager) — khusus Master --}}
        @if (auth()->user()->isMaster())
            <section class="pm-form-section" x-show="role === 'manager'" x-cloak>
                <div class="row g-4">
                    <div class="col-lg-4">
                        <h2 class="pm-form-section__title">{{ __('3. Wilayah yang dipegang') }}</h2>
                        <p class="pm-form-section__desc">{{ __('Manager hanya melihat & mengurus villa di wilayah yang dicentang, termasuk laba bersihnya. Boleh lebih dari satu wilayah.') }}</p>
                    </div>
                    <div class="col-lg-8">
                        @if ($regions->isEmpty())
                            <div class="alert alert-warning d-flex gap-2 mb-0">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                <div>{{ __('Belum ada wilayah.') }} <a href="{{ route('regions.create') }}" class="alert-link">{{ __('Buat wilayah') }}</a> {{ __('dulu, lalu tugaskan manager ini.') }}</div>
                            </div>
                        @else
                            <div class="row g-2">
                                @foreach ($regions as $rg)
                                    <div class="col-sm-6">
                                        <label class="pm-choice align-items-center py-2">
                                            <input type="checkbox" name="region_ids[]" value="{{ $rg->id }}" class="form-check-input mt-0" @checked(in_array($rg->id, $selectedRegionIds, true))>
                                            <span class="min-w-0">
                                                <span class="pm-choice__title d-block text-truncate">{{ $rg->name }}</span>
                                                <span class="pm-choice__desc d-block text-truncate">{{ __(':count villa', ['count' => $rg->villas_count]) }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('region_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- 3. Akses villa --}}
        <section class="pm-form-section" x-show="role === 'staff' || role === 'owner'" x-cloak>
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('3. Akses villa') }}</h2>
                    <p class="pm-form-section__desc" x-show="role === 'staff'">{{ __('Staff hanya melihat kalender & mengunggah nota untuk villa yang dicentang. Boleh lebih dari satu.') }}</p>
                    <p class="pm-form-section__desc" x-show="role === 'owner'">{{ __('Owner hanya melihat data keuangan villa yang dicentang. Jika villa sudah punya owner lain, owner-nya akan diganti.') }}</p>
                </div>
                <div class="col-lg-8">
                    @if ($villas->isEmpty())
                        <div class="alert alert-warning d-flex gap-2 mb-0">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <div>{{ __('Belum ada villa. Simpan akun ini dulu, lalu atur aksesnya saat') }} <a href="{{ route('villas.create') }}" class="alert-link">{{ __('menambah villa') }}</a>.</div>
                        </div>
                    @else
                        <div class="row g-2">
                            @foreach ($villas as $v)
                                <div class="col-sm-6">
                                    <label class="pm-choice align-items-center py-2">
                                        <input type="checkbox" name="villa_ids[]" value="{{ $v->id }}" class="form-check-input mt-0"
                                               @checked(in_array($v->id, $selectedVillaIds, true))>
                                        <span class="min-w-0">
                                            <span class="pm-choice__title d-block text-truncate">{{ $v->name }}
                                                @unless ($v->is_active)<span class="fs-xs text-muted fw-normal">{{ __('(nonaktif)') }}</span>@endunless
                                            </span>
                                            <span class="pm-choice__desc d-block text-truncate" x-show="role === 'owner'">
                                                @if ($v->owner && $v->owner_id !== $user->id)
                                                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ __('Owner saat ini: :name', ['name' => $v->owner->name]) }}
                                                @elseif (! $v->owner)
                                                    {{ __('Belum ada owner') }}
                                                @else
                                                    {{ __('Milik akun ini') }}
                                                @endif
                                            </span>
                                            <span class="pm-choice__desc d-block text-truncate" x-show="role === 'staff'">{{ $v->location ?: '—' }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('villa_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('villa_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
            </div>
        </section>

        <x-form.actions :cancel="route('users.index')" :submit="$isEdit ? __('Simpan Perubahan') : __('Buat Akun')" :icon="$isEdit ? 'bi-check-lg' : 'bi-person-plus'" />
    </div>
</form>

@if ($isEdit && ! $isSelf)
    <div class="mt-3 d-flex justify-content-end">
        <form method="POST" action="{{ route('users.destroy', $user) }}"
              data-confirm="{{ __('Hapus akun :name?', ['name' => $user->name]) }}"
              data-confirm-text="{{ __('Akun tidak bisa login lagi dan akses villanya dicabut. Transaksi yang pernah diinput tetap tersimpan untuk laporan.') }}"
              data-confirm-button="{{ __('Ya, hapus akun') }}" data-confirm-danger>
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger-soft"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus akun') }}</button>
        </form>
    </div>
@endif
