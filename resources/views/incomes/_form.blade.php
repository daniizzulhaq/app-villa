{{--
  Form pemasukan. Pratinjau Net / Fee / Revenue After Fee dihitung langsung saat mengetik,
  supaya Manager bisa mengecek angka sebelum menyimpan. Angka final dihitung ulang di server
  (FinanceCalculator) memakai % fee yang berlaku pada tanggal bayar.
--}}
@php $isEdit = $income->exists; @endphp
<form method="POST" enctype="multipart/form-data" data-validate novalidate
      action="{{ $isEdit ? route('incomes.update', $income) : route('incomes.store') }}"
      x-data="{
          fees: @js($villaFees), custom: @js($customBasis),
          villa: @js((string) old('villa_id', $income->villa_id)),
          isNet: @js((bool) old('is_net_of_platform', $income->is_net_of_platform)),
          gross: 0, pf: 0, od: 0, base: 0,
          get fee() { return this.fees[this.villa] || null },
          get net() { return this.gross - (this.isNet ? 0 : this.pf) - this.od },
          get feeBase() { if (!this.fee) return 0; return this.fee.basis === 'gross' ? this.gross : (this.fee.basis === this.custom ? this.base : this.net) },
          get feeAmount() { return this.fee ? Math.round(this.feeBase * this.fee.pct) / 100 : 0 },
          get after() { return this.net - this.feeAmount },
          recalc() { const f = new FormData($el); const n = k => Number(f.get(k) || 0); this.gross = n('gross_amount'); this.pf = n('platform_fee'); this.od = n('other_deduction'); this.base = n('fee_base_amount'); },
          rp(v) { return window.rupiahFull(v) }
      }" x-init="$nextTick(() => recalc())" @input.debounce.60ms="recalc()" @change="recalc()">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <x-ui.form-navigation :steps="['income-booking' => __('Reservasi & villa'), 'income-amount' => __('Nominal'), 'income-proof' => __('Catatan & bukti bayar')]" />

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>{{ __('Ada :count isian yang perlu diperbaiki.', ['count' => $errors->count()]) }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="pm-card">
                <section class="pm-form-section" id="income-booking">
                    <h2 class="pm-form-section__title mb-3">{{ __('1. Reservasi & villa') }}</h2>
                    <x-form.select name="booking_id" :label="__('Terhubung ke reservasi')" :options="$bookings" :value="$income->booking_id"
                                   :placeholder="__('— Tanpa reservasi (pemasukan lain) —')"
                                   :help="__('Pilih reservasi agar villa, sumber & kode booking terisi dan pendapatan tampil di detail reservasi.')" />
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="mb-3" data-field>
                                <label for="f_villa_id" class="form-label">{{ __('Villa') }}<span class="req" aria-hidden="true">*</span></label>
                                <select name="villa_id" id="f_villa_id" class="form-select @error('villa_id') is-invalid @enderror" x-model="villa" required data-label="{{ __('Villa') }}">
                                    <option value="">{{ __('Pilih villa') }}</option>
                                    @foreach ($villas as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                                </select>
                                @error('villa_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text" x-show="fee" x-cloak>{{ __('Fee villa:') }} <strong x-text="fee?.label"></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6"><x-form.select name="platform_id" :label="__('Sumber booking')" :options="$platforms" :value="$income->platform_id" :placeholder="__('Pilih sumber')" required /></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><x-form.input name="booking_ref" :label="__('Kode booking')" :value="$income->booking_ref" maxlength="100" :help="__('Dipakai untuk mencegah data dobel saat import.')" /></div>
                        <div class="col-sm-6"><x-form.input type="date" name="payment_date" :label="__('Tanggal bayar / payout')" :value="$income->payment_date?->format('Y-m-d')" required :help="__('Menentukan bulan pemasukan di laporan.')" /></div>
                    </div>
                </section>

                <section class="pm-form-section" id="income-amount">
                    <h2 class="pm-form-section__title mb-3">{{ __('2. Nominal') }}</h2>
                    <x-form.money name="gross_amount" :label="__('Gross booking income')" :value="$income->gross_amount" required :help="__('Total yang dibayar tamu.')" />
                    <div class="mb-3">
                        <label class="pm-choice align-items-center py-2">
                            <input type="hidden" name="is_net_of_platform" value="0">
                            <input type="checkbox" name="is_net_of_platform" value="1" class="form-check-input mt-0" x-model="isNet">
                            <span>
                                <span class="pm-choice__title d-block">{{ __('Nominal di atas sudah dipotong komisi platform') }}</span>
                                <span class="pm-choice__desc d-block">{{ __('Centang bila mengisi angka payout (sudah net). Komisi tetap boleh dicatat sebagai info, tetapi tidak dipotong dua kali.') }}</span>
                            </span>
                        </label>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><x-form.money name="platform_fee" :label="__('Komisi platform')" :value="$income->platform_fee" /></div>
                        <div class="col-sm-6"><x-form.money name="other_deduction" :label="__('Potongan lain')" :value="$income->other_deduction" :help="__('mis. pajak, biaya transfer, diskon')" /></div>
                    </div>
                    <div x-show="fee && fee.basis === custom" x-cloak>
                        <x-form.money name="fee_base_amount" :label="__('Basis fee (sesuai kesepakatan)')" :value="$income->fee_basis?->value === 'custom' ? $income->fee_base_amount : null"
                                      :help="__('Villa ini memakai basis fee custom. Isi nominal yang menjadi dasar perhitungan fee.')" />
                    </div>
                </section>

                <section class="pm-form-section" id="income-proof">
                    <h2 class="pm-form-section__title mb-3">{{ __('3. Catatan & bukti bayar') }}</h2>
                    <x-form.textarea name="notes" :label="__('Catatan')" :value="$income->notes" rows="2" maxlength="3000" />
                    @if ($isEdit && $income->attachments->isNotEmpty())
                        <div class="pm-gallery mb-3" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr))">
                            @foreach ($income->attachments as $a)
                                <label class="pm-thumb" x-data="{ rm: false }" :style="rm && 'opacity:.35'">
                                    @if (str_ends_with($a->path, '.pdf'))
                                        <span class="d-grid h-100 place-items-center text-muted" style="place-items:center"><i class="bi bi-file-earmark-pdf fs-1" aria-hidden="true"></i></span>
                                    @else
                                        <img src="{{ route('files.income', $a) }}" alt="{{ $a->original_name }}" loading="lazy">
                                    @endif
                                    <input type="checkbox" name="remove_attachments[]" value="{{ $a->id }}" class="visually-hidden" x-model="rm">
                                    <span class="pm-thumb__cap" x-text="rm ? {{ Js::from(__('Akan dihapus — ketuk untuk batal')) }} : {{ Js::from(__('Ketuk untuk hapus')) }}"></span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <div class="mb-0" data-field>
                        <label class="form-label" for="f_attachments">{{ __('Bukti bayar (screenshot payout / transfer)') }}</label>
                        <input type="file" id="f_attachments" name="attachments[]" class="form-control @error('attachments.*') is-invalid @enderror" accept="image/*,application/pdf" multiple>
                        @error('attachments.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('JPG/PNG/PDF, maks. 5 file @ 10 MB. Disimpan privat.') }}</div>
                    </div>
                </section>

                <x-form.actions :cancel="$isEdit ? route('incomes.show', $income) : route('incomes.index')" :submit="$isEdit ? __('Simpan Perubahan') : __('Simpan Pemasukan')" />
            </div>
        </div>

        {{-- Pratinjau perhitungan --}}
        <div class="col-xl-4">
            <div class="pm-card pm-card__body pm-form-preview position-sticky" style="top: calc(var(--pm-topbar-h) + 16px)">
                <span class="pm-form-preview__icon" aria-hidden="true"><i class="bi bi-calculator"></i></span>
                <h2 class="pm-card__title mb-1">{{ __('Pratinjau perhitungan') }}</h2>
                <p class="fs-sm text-muted mb-3">{{ __('Angka final dihitung ulang memakai % fee yang berlaku pada tanggal bayar.') }}</p>
                <table class="pm-ledger" aria-live="polite">
                    <tr><td>{{ __('Gross booking income') }}</td><td x-text="rp(gross)"></td></tr>
                    <tr class="sub minus"><td x-text="isNet ? {{ Js::from(__('Komisi platform (sudah termasuk)')) }} : {{ Js::from(__('Komisi platform')) }}"></td><td x-text="rp(isNet ? 0 : pf)"></td></tr>
                    <tr class="sub minus"><td>{{ __('Potongan lain') }}</td><td x-text="rp(od)"></td></tr>
                    <tr><td><strong>{{ __('Net booking revenue') }}</strong></td><td x-text="rp(net)"></td></tr>
                    <tr class="sub minus"><td x-text="fee ? {{ Js::from(__('Management fee')) }} + ' ' + fee.pct.toLocaleString('id-ID') + '%' : {{ Js::from(__('Management fee')) }}"></td><td x-text="rp(feeAmount)"></td></tr>
                    <tr class="total"><td>{{ __('Revenue after fee') }}</td><td :class="after < 0 ? 'text-negative' : 'text-positive'" x-text="rp(after)"></td></tr>
                </table>
                <p class="fs-xs text-muted mt-3 mb-0" x-show="fee" x-cloak>{{ __('Basis fee:') }} <span x-text="rp(feeBase)"></span></p>
                <div class="pm-form-help"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ __('Periksa ringkasan sebelum menyimpan pemasukan.') }}</span></div>
            </div>
        </div>
    </div>
</form>
