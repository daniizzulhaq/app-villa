@php $isEdit = $region->exists; @endphp

<form method="POST" data-validate novalidate action="{{ $isEdit ? route('regions.update', $region) : route('regions.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
            <div>{{ __('Ada :count isian yang perlu diperbaiki.', ['count' => $errors->count()]) }}</div>
        </div>
    @endif

    <div class="pm-card">
        {{-- 1. Info --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('1. Nama wilayah') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Mis. Ubud, Canggu, Seminyak. Dipakai juga sebagai filter di laporan.') }}</p>
                </div>
                <div class="col-lg-8">
                    <x-form.input name="name" :label="__('Nama wilayah')" :value="$region->name" required maxlength="100" :placeholder="__('contoh: Ubud')" />
                    <x-form.input name="description" :label="__('Keterangan (opsional)')" :value="$region->description" maxlength="255" class="mb-0" />
                </div>
            </div>
        </section>

        {{-- 2. Villa --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('2. Villa di wilayah ini') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Satu villa hanya bisa berada di satu wilayah. Mencentang villa dari wilayah lain akan memindahkannya ke sini.') }}</p>
                </div>
                <div class="col-lg-8">
                    @if ($villas->isEmpty())
                        <p class="text-muted mb-0">{{ __('Belum ada villa.') }}</p>
                    @else
                        <div class="row g-2">
                            @foreach ($villas as $v)
                                <div class="col-sm-6">
                                    <label class="pm-choice align-items-center py-2">
                                        <input type="checkbox" name="villa_ids[]" value="{{ $v->id }}" class="form-check-input mt-0" @checked(in_array($v->id, $selectedVillaIds, true))>
                                        <span class="min-w-0">
                                            <span class="pm-choice__title d-block text-truncate">{{ $v->name }}
                                                @unless ($v->is_active)<span class="fs-xs text-muted fw-normal">{{ __('(nonaktif)') }}</span>@endunless
                                            </span>
                                            <span class="pm-choice__desc d-block text-truncate">
                                                @if ($v->region && $v->region_id !== $region->id)
                                                    <i class="bi bi-arrow-left-right" aria-hidden="true"></i> {{ __('Saat ini di :region', ['region' => $v->region->name]) }}
                                                @elseif (! $v->region)
                                                    {{ __('Belum punya wilayah') }}
                                                @else
                                                    {{ $v->location ?: '—' }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('villa_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Manager --}}
        <section class="pm-form-section">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="pm-form-section__title">{{ __('3. Manager wilayah') }}</h2>
                    <p class="pm-form-section__desc">{{ __('Manager yang dicentang bisa melihat & mengurus semua villa di wilayah ini, termasuk laba bersihnya. Satu manager boleh memegang beberapa wilayah.') }}</p>
                </div>
                <div class="col-lg-8">
                    @if ($managers->isEmpty())
                        <div class="alert alert-info d-flex gap-2 mb-0">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <div>{{ __('Belum ada akun Manager.') }} <a href="{{ route('users.create', ['role' => 'manager']) }}" class="alert-link">{{ __('Buat akun Manager') }}</a>.</div>
                        </div>
                    @else
                        <div class="row g-2">
                            @foreach ($managers as $m)
                                <div class="col-sm-6">
                                    <label class="pm-choice align-items-center py-2">
                                        <input type="checkbox" name="manager_ids[]" value="{{ $m->id }}" class="form-check-input mt-0" @checked(in_array($m->id, $selectedManagerIds, true))>
                                        <span class="min-w-0">
                                            <span class="pm-choice__title d-block text-truncate">{{ $m->name }}
                                                @unless ($m->is_active)<span class="fs-xs text-muted fw-normal">{{ __('(nonaktif)') }}</span>@endunless
                                            </span>
                                            <span class="pm-choice__desc d-block text-truncate">{{ $m->email }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <x-form.actions :cancel="route('regions.index')" :submit="$isEdit ? __('Simpan Perubahan') : __('Simpan Wilayah')" icon="bi-check-lg" />
    </div>
</form>

@if ($isEdit)
    <div class="mt-3 d-flex justify-content-end">
        <form method="POST" action="{{ route('regions.destroy', $region) }}"
              data-confirm="{{ __('Hapus wilayah :name?', ['name' => $region->name]) }}"
              data-confirm-text="{{ __('Villa di wilayah ini tidak terhapus, hanya menjadi tanpa wilayah (sementara hanya Master yang bisa mengaksesnya).') }}"
              data-confirm-button="{{ __('Ya, hapus') }}" data-confirm-danger>
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-light text-danger"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus wilayah') }}</button>
        </form>
    </div>
@endif
