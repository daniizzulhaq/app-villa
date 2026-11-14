{{--
  Form nota mobile-first, urutan mengikuti kerja di lapangan:
  Villa → Foto nota → Nominal → Catatan → Kategori → Tanggal.
  Keterangan tambahan, vendor & metode bayar tersedia di "Detail lainnya".
--}}
@php
    $isEdit = $expense->exists;
    $existingReceipts = $isEdit ? $expense->receipts : collect();
    $selectedCat = (string) old('category_id', $expense->category_id);
    $showMore = $errors->hasAny(['vendor', 'payment_method', 'description']) || ($isEdit && $expense->vendor);
@endphp
<form method="POST" enctype="multipart/form-data" data-validate novalidate
      x-data="{ receiptsBusy: false }" @receipts-processing="receiptsBusy = $event.detail"
      action="{{ $isEdit ? route('expenses.update', $expense) : route('expenses.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($isEdit && $expense->status === \App\Enums\ExpenseStatus::Returned)
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            <div><strong>{{ __('Dikembalikan Manager:') }}</strong> {{ $expense->return_reason }}<div class="fs-sm mt-1">{{ __('Perbaiki data di bawah lalu ketuk') }} <strong>{{ __('Kirim Ulang') }}</strong>.</div></div>
        </div>
    @elseif ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>{{ __('Ada :count isian yang perlu diperbaiki.', ['count' => $errors->count()]) }}</div></div>
    @endif

    <div class="row g-3 justify-content-center">
        <div class="col-xl-8 col-xxl-7">
            <x-ui.form-navigation :steps="['expense-villa' => __('Villa'), 'expense-photo' => __('Foto nota'), 'expense-details' => __('Nominal & kategori')]" />
            <div class="pm-card">
                {{-- 1. Villa --}}
                <section class="pm-form-section" id="expense-villa">
                    <h2 class="pm-form-section__title mb-3"><span class="text-muted">1.</span> {{ __('Villa') }}</h2>
                    @if ($villas->count() === 1 && ! $isEdit)
                        <input type="hidden" name="villa_id" value="{{ $villas->first()->id }}">
                        <div class="mb-3"><span class="form-label d-block">{{ __('Villa') }}</span><div class="form-control bg-light d-flex align-items-center">{{ $villas->first()->name }}</div></div>
                    @else
                        <x-form.select name="villa_id" :label="__('Villa')" :options="$villas->pluck('name', 'id')" :value="$expense->villa_id" :placeholder="__('Pilih villa')" required />
                    @endif
                </section>

                {{-- 2. Foto --}}
                <section class="pm-form-section" id="expense-photo" x-data="receiptPicker({ max: {{ $maxFiles }}, existing: {{ $existingReceipts->count() }}, maxBytes: {{ (int) config('propertyme.upload.max_kb', 10240) * 1024 }}, maxSide: {{ (int) config('propertyme.upload.max_side', 1600) }} })" :aria-busy="processing">
                    <h2 class="pm-form-section__title mb-3"><span class="text-muted">2.</span> {{ __('Foto nota') }}</h2>

                    @if ($existingReceipts->isNotEmpty())
                        <div class="pm-gallery mb-3" style="grid-template-columns: repeat(auto-fill, minmax(96px, 1fr))">
                            @foreach ($existingReceipts as $r)
                                <label class="pm-thumb" x-data="{ rm: false }" :style="rm && 'opacity:.35'">
                                    @if (str_ends_with($r->path, '.pdf'))
                                        <span class="d-grid h-100 text-muted" style="place-items:center"><i class="bi bi-file-earmark-pdf fs-1" aria-hidden="true"></i></span>
                                    @else
                                        <img src="{{ route('files.receipt', $r) }}" alt="{{ __('Nota tersimpan') }}" loading="lazy">
                                    @endif
                                    <input type="checkbox" name="remove_receipts[]" value="{{ $r->id }}" class="visually-hidden" x-model="rm" @change="existing += rm ? -1 : 1">
                                    <span class="pm-thumb__cap" x-text="rm ? {{ Js::from(__('Dihapus · batal?')) }} : {{ Js::from(__('Ketuk untuk hapus')) }}"></span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="row g-2">
                        <div class="col-7 col-sm-8">
                            <label class="pm-camera-btn h-100 mb-0" for="f_camera">
                                <i class="bi bi-camera-fill" aria-hidden="true"></i>
                                <span x-text="count ? {{ Js::from(__('Foto lagi')) }} : {{ Js::from(__('Foto Nota')) }}"></span>
                                <span class="fs-xs fw-normal">{{ __('Kamera langsung terbuka') }}</span>
                            </label>
                            {{-- input kamera terpisah agar capture tetap jalan; hasilnya digabung ke input utama --}}
                            <input type="file" id="f_camera" accept="image/*" capture="environment" class="visually-hidden" :disabled="processing" @change="add($event); $event.target.value=''">
                        </div>
                        <div class="col-5 col-sm-4">
                            <label class="pm-camera-btn h-100 mb-0" for="f_gallery" style="border-style: solid; background: #fff">
                                <i class="bi bi-images" aria-hidden="true"></i>
                                <span>{{ __('Dari galeri') }}</span>
                                <span class="fs-xs fw-normal">{{ __('Bisa pilih banyak') }}</span>
                            </label>
                            <input type="file" id="f_gallery" accept="image/*,application/pdf" multiple class="visually-hidden" :disabled="processing" @change="add($event); $event.target.value=''">
                        </div>
                    </div>
                    <input type="file" name="receipts[]" multiple x-ref="input" class="d-none" aria-hidden="true" tabindex="-1">

                    <template x-if="files.length">
                        <div class="pm-gallery mt-3" style="grid-template-columns: repeat(auto-fill, minmax(96px, 1fr))">
                            <template x-for="(f, i) in files" :key="i">
                                <div class="pm-thumb">
                                    <template x-if="previews[i]"><img :src="previews[i]" :alt="{{ Js::from(__('Foto nota')) }} + ' ' + (i + 1)"></template>
                                    <template x-if="!previews[i]"><span class="d-grid h-100 text-muted" style="place-items:center"><i class="bi bi-file-earmark-pdf fs-1"></i></span></template>
                                    <button type="button" class="pm-thumb__remove" @click="remove(i)" :aria-label="{{ Js::from(__('Hapus foto')) }} + ' ' + (i + 1)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div class="form-text" role="status" aria-live="polite" x-text="processing ? {{ Js::from(__('Preparing receipt photos…')) }} : window.t(':count of :max files · photos optimized automatically', { count, max: {{ $maxFiles }} })"></div>
                    @error('receipts')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @error('receipts.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </section>

                {{-- 3. Data --}}
                <section class="pm-form-section" id="expense-details">
                    <h2 class="pm-form-section__title mb-3"><span class="text-muted">3.</span> {{ __('Nominal & kategori') }}</h2>
                    @if ($expense->recurring_expense_id)
                        @include('expenses._recurring-amount')
                    @else
                        <x-form.money name="amount" :label="__('Nominal')" :value="$expense->amount" required class="fs-5" />
                    @endif
                    <x-form.textarea name="notes" :label="__('Catatan')" :value="$expense->notes" rows="2" maxlength="3000" />

                    <fieldset class="mb-3">
                        <legend class="form-label">{{ __('Kategori') }}<span class="req" aria-hidden="true">*</span></legend>
                        <div class="pm-cat-grid">
                            @foreach ($categories as $c)
                                <label class="pm-cat">
                                    <input type="radio" name="category_id" value="{{ $c->id }}" class="visually-hidden" required data-label="{{ __('Kategori') }}" @checked($selectedCat === (string) $c->id)>
                                    <i class="bi {{ $c->icon }}" aria-hidden="true"></i>{{ __($c->name) }}
                                </label>
                            @endforeach
                        </div>
                        @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>

                    <x-form.input type="date" name="expense_date" :label="__('Tanggal')" :value="$expense->expense_date?->format('Y-m-d')" required :max="today()->addDay()->format('Y-m-d')" />

                    <details @if($showMore) open @endif class="pm-form-details mt-1">
                        <summary class="mb-3">{{ __('Detail lainnya (keterangan, vendor, metode bayar)') }}</summary>
                        <x-form.input name="description" :label="__('Keterangan')" :value="$expense->description" maxlength="255" :placeholder="__('Kosongkan = nama kategori')" />
                        <div class="row">
                            <div class="col-sm-6"><x-form.input name="vendor" :label="__('Vendor / toko')" :value="$expense->vendor" maxlength="150" :placeholder="__('mis. Toko Bangunan Jaya')" /></div>
                            <div class="col-sm-6"><x-form.select name="payment_method" :label="__('Metode bayar')" :options="$methods" :value="$expense->payment_method" placeholder="—" /></div>
                        </div>
                    </details>
                </section>

                <div class="pm-form-actions">
                    <a href="{{ auth()->user()->isStaff() ? route('staff.activity') : ($isEdit ? route('expenses.show', $expense) : route('expenses.index')) }}" class="btn btn-light">{{ __('Batal') }}</a>
                    @unless ($isEdit)
                        <button type="submit" name="after" value="another" class="btn btn-light d-none d-sm-inline-flex" :disabled="receiptsBusy" data-loading-text="{{ __('Mengirim…') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Simpan & tambah lagi') }}</button>
                    @endunless
                    <button type="submit" class="btn btn-primary btn-lg" :disabled="receiptsBusy" data-loading-text="{{ __('Mengunggah…') }}">
                        <i class="bi bi-send" aria-hidden="true"></i>
                        {{ $isEdit ? ($expense->status === \App\Enums\ExpenseStatus::Returned && auth()->user()->isStaff() ? __('Kirim Ulang') : __('Simpan')) : (auth()->user()->isStaff() ? __('Kirim Nota') : __('Simpan')) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
