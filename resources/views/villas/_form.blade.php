{{--
  Form villa dibagi 5 bagian bernomor agar Manager tidak kewalahan:
  1 Info dasar · 2 Owner · 3 Management fee · 4 Link iCal · 5 Staff
--}}
@php
    $isEdit = $villa->exists;
    $currentBasis = old('fee_basis', $villa->fee_basis?->value);
    $selectedStaff = array_map('intval', old('staff_ids', $isEdit ? $villa->staff->pluck('id')->all() : []));
    $origPct = (string) $villa->getOriginal('fee_percentage');
    $origBasis = $villa->getOriginal('fee_basis')?->value ?? $villa->getOriginal('fee_basis');
@endphp

<form method="POST" enctype="multipart/form-data" data-validate novalidate
      action="{{ $isEdit ? route('villas.update', $villa) : route('villas.store') }}"
      x-data="{
          basis: @js($currentBasis),
          pct: @js((string) old('fee_percentage', $villa->fee_percentage)),
          origPct: @js($origPct), origBasis: @js($origBasis), isEdit: @js($isEdit),
          get feeChanged() { return this.isEdit && (parseFloat(String(this.pct).replace(',', '.')) !== parseFloat(this.origPct) || this.basis !== this.origBasis) }
      }">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
            <div>{{ __('Ada :count isian yang perlu diperbaiki. Periksa kolom bertanda merah di bawah.', ['count' => $errors->count()]) }}</div>
        </div>
    @endif

    <div class="pm-card">
        {{-- 1. Info dasar --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('1. Informasi villa') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Nama dan lokasi tampil di kalender, laporan, dan PDF untuk owner.') }}</p>
                </div>
                <div class="col-lg-8">
                    <x-form.input name="name" :label="__('Nama villa')" :value="$villa->name" required maxlength="150" :placeholder="__('contoh: Villa Kayu Manis')" />
                    <x-form.input name="location" :label="__('Lokasi')" :value="$villa->location" maxlength="255" :placeholder="__('contoh: Jl. Raya Ubud No. 12, Gianyar')" />

                    {{-- Foto dengan pratinjau --}}
                    <div class="mb-3" x-data="photoPicker(@js($villa->photoUrl()))" data-field>
                        <span class="form-label d-block">{{ __('Foto villa') }}</span>
                        <label class="pm-photo-drop mb-0" for="f_photo">
                            <template x-if="preview"><img :src="preview" alt="{{ __('Pratinjau foto villa') }}"></template>
                            <template x-if="!preview"><span class="pm-villa-thumb" style="width:96px;height:72px" aria-hidden="true"><i class="bi bi-image"></i></span></template>
                            <span>
                                <span class="fw-semibold text-ink d-block" x-text="preview ? {{ Js::from(__('Ganti foto')) }} : {{ Js::from(__('Pilih foto')) }}"></span>
                                <span class="fs-sm text-muted">{{ __('JPG/PNG/WebP, maks. 10 MB. Otomatis dikompres.') }}</span>
                            </span>
                        </label>
                        <input type="file" name="photo" id="f_photo" accept="image/jpeg,image/png,image/webp" class="visually-hidden" x-ref="file" @change="pick">
                        <input type="hidden" name="remove_photo" :value="removed ? 1 : 0">
                        <button type="button" class="btn btn-sm btn-ghost text-danger mt-1" x-show="preview" x-cloak @click="clear">
                            <i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus foto') }}
                        </button>
                        @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <x-form.input type="date" name="started_at" :label="__('Mulai dikelola')" :value="$villa->started_at?->format('Y-m-d')" />
                        </div>
                        <div class="col-sm-6">
                            <x-form.select name="timezone" :label="__('Zona waktu')" :options="$timezones" :value="$villa->timezone" required
                                           :help="__('Menentukan bulan transaksi di laporan.')" />
                        </div>
                    </div>
                    <x-form.textarea name="notes" :label="__('Catatan internal')" :value="$villa->notes" rows="2" maxlength="5000"
                                     :placeholder="__('contoh: kode gerbang, kontak tukang kolam')" />
                    @if ($isMaster)
                        <x-form.switch name="is_active" :label="__('Villa aktif')" :checked="$villa->is_active"
                                       :help="__('Villa nonaktif tidak muncul di pilihan Staff, tetapi data lamanya tetap tersimpan.')" class="mb-0" />
                    @endif
                </div>
            </div>
        </section>

        {{-- 2. Owner & wilayah (khusus Master) --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('2. Pemilik & wilayah') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Owner hanya dapat melihat villa yang terhubung dengan akunnya. Wilayah menentukan Manager yang mengurus villa ini.') }} {{ __('Total maksimal :limit villa per akun.', ['limit' => config('propertyme.villa_limits.owner')]) }}</p>
                </div>
                <div class="col-lg-8">
                    @if (! $isMaster)
                        <dl class="pm-dl mb-0">
                            <dt>{{ __('Owner') }}</dt><dd>{{ $villa->owner?->name ?? __('Belum ditentukan') }}</dd>
                            <dt>{{ __('Wilayah') }}</dt><dd>{{ $villa->region?->name ?? '—' }}</dd>
                        </dl>
                        <p class="form-text mb-0"><i class="bi bi-lock" aria-hidden="true"></i> {{ __('Owner & wilayah hanya dapat diubah Master.') }}</p>
                    @else
                    <x-form.select name="region_id" :label="__('Wilayah')" :options="$regions" :value="$villa->region_id" :placeholder="__('— Tanpa wilayah (hanya Master) —')"
                                   :help="__('Manager wilayah ini otomatis bisa mengurus villa ini.')" />
                    @if ($owners->isEmpty())
                        <div class="alert alert-warning d-flex gap-2 mb-0">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <div>{{ __('Belum ada akun Owner.') }} <a href="{{ route('users.create', ['role' => 'owner']) }}" class="alert-link">{{ __('Buat akun Owner') }}</a> {{ __('lalu kembali ke sini — atau simpan dulu, owner bisa diatur nanti.') }}</div>
                        </div>
                    @else
                        <x-form.select name="owner_id" :label="__('Owner')" :options="$owners" :value="$villa->owner_id" :placeholder="__('— Belum ditentukan —')" class="mb-0" />
                    @endif
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Management fee (khusus Master) --}}
        @if (! $isMaster)
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('3. Management fee') }}</h2>
                </div>
                <div class="col-lg-8">
                    <div class="fw-semibold text-ink">{{ $villa->feeLabel() }}</div>
                    <p class="form-text mb-0"><i class="bi bi-lock" aria-hidden="true"></i> {{ __('% fee menyangkut uang owner sehingga hanya dapat diubah Master.') }}</p>
                </div>
            </div>
        </section>
        @else
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('3. Management fee') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Dihitung otomatis di setiap pemasukan. Perubahan fee tidak mengubah bulan yang sudah lewat — tersimpan sebagai riwayat.') }}</p>
                </div>
                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-sm-6">
                            <x-form.input type="number" name="fee_percentage" :label="__('Persentase fee')" :value="$villa->fee_percentage"
                                          suffix="%" required min="0" max="100" step="0.01" inputmode="decimal" x-model="pct" />
                        </div>
                    </div>

                    <fieldset class="mb-3">
                        <legend class="form-label">{{ __('Basis perhitungan fee') }}<span class="req" aria-hidden="true">*</span></legend>
                        <div class="row g-2">
                            @foreach ($feeBases as $basis)
                                <div class="col-md-4">
                                    <label class="pm-choice">
                                        <input type="radio" name="fee_basis" value="{{ $basis->value }}" class="form-check-input" x-model="basis" required
                                               @checked($currentBasis === $basis->value)>
                                        <span>
                                            <span class="pm-choice__title d-block">{{ $basis->label() }}</span>
                                            <span class="pm-choice__desc d-block">{{ $basis->help() }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('fee_basis')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>

                    <div x-show="basis === 'custom'" x-cloak>
                        <x-form.input name="fee_basis_note" :label="__('Keterangan basis yang disepakati')" :value="$villa->fee_basis_note" maxlength="255"
                                      :placeholder="__('contoh: Net revenue dikurangi biaya listrik')" x-bind:required="basis === 'custom'" />
                    </div>

                    {{-- Tanggal berlaku: wajib dipikirkan saat fee diubah --}}
                    <div x-show="!isEdit || feeChanged" x-cloak>
                        <div class="alert alert-info d-flex gap-2 py-2" x-show="feeChanged">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                            <div class="fs-sm">{{ __('Fee berubah. Tentukan mulai kapan fee baru berlaku — pemasukan sebelum tanggal ini tetap memakai fee lama.') }}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <x-form.input type="date" name="fee_effective_from" :label="__('Fee berlaku mulai')"
                                              :value="$isEdit ? today()->startOfMonth()->format('Y-m-d') : ($villa->started_at?->format('Y-m-d') ?? today()->format('Y-m-d'))" class="mb-0" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @endif

        {{-- 4. iCal --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('4. Link iCal platform') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Reservasi dari Airbnb & Booking.com masuk otomatis tiap 30 menit. Kosongkan jika villa tidak terdaftar di platform tersebut.') }}</p>
                </div>
                <div class="col-lg-8">
                    @foreach ($icalPlatforms as $platform)
                        @php $channel = $isEdit ? $villa->channels->firstWhere('platform_id', $platform->id) : null; @endphp
                        <x-form.input type="url" name="channels[{{ $platform->slug }}]" :label="__('Link iCal :platform', ['platform' => $platform->name])"
                                      :value="$channel?->ical_url" maxlength="2000" inputmode="url"
                                      :placeholder="$platform->slug === 'airbnb' ? 'https://www.airbnb.com/calendar/ical/....ics' : 'https://admin.booking.com/hotel/hoteladmin/ical.html?t=...'"
                                      :help="$platform->slug === 'airbnb'
                                        ? __('Airbnb: Kalender → Ketersediaan → Hubungkan kalender → Ekspor kalender.')
                                        : __('Booking.com Extranet: Tarif & Ketersediaan → Sinkronisasi kalender → Ekspor.')" />
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 5. Staff --}}
        <section class="pm-form-section" id="staff">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('5. Staff yang bertugas') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Staff hanya melihat kalender dan dapat mengunggah nota untuk villa yang dicentang. Satu staff boleh menangani beberapa villa.') }} {{ __('Total maksimal :limit villa per akun.', ['limit' => config('propertyme.villa_limits.staff')]) }}</p>
                </div>
                <div class="col-lg-8">
                    @if ($staffUsers->isEmpty())
                        <div class="alert alert-warning d-flex gap-2 mb-0">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <div>{{ __('Belum ada akun Staff.') }} <a href="{{ route('users.create', ['role' => 'staff']) }}" class="alert-link">{{ __('Buat akun Staff') }}</a>.</div>
                        </div>
                    @else
                        <div class="row g-2">
                            @foreach ($staffUsers as $s)
                                <div class="col-sm-6">
                                    <label class="pm-choice align-items-center py-2">
                                        <input type="checkbox" name="staff_ids[]" value="{{ $s->id }}" class="form-check-input mt-0" @checked(in_array($s->id, $selectedStaff, true))>
                                        <x-ui.avatar :user="$s" />
                                        <span class="min-w-0">
                                            <span class="pm-choice__title d-block text-truncate">{{ $s->name }}</span>
                                            <span class="pm-choice__desc d-block">{{ $s->phone ?: __('No. HP belum diisi') }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('staff_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('staff_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
            </div>
        </section>

        <x-form.actions :cancel="$isEdit ? route('villas.show', $villa) : route('villas.index')"
                        :submit="$isEdit ? __('Simpan Perubahan') : __('Simpan Villa')" />
    </div>
</form>
