@php
    $base = old('base_amount', $expense->base_amount);
    $additions = old('additions', $expense->additions ?? []);
@endphp
<div x-data="{
    base: {{ Js::from($base) }},
    rows: {{ Js::from(array_values($additions)) }},
    nextKey: {{ count($additions) }},
    init() { this.rows = this.rows.map((row, key) => ({ ...row, key })); },
    get total() { return Number(this.base || 0) + this.rows.reduce((sum, row) => sum + Number(row.amount || 0), 0); }
}">
    <div class="alert alert-info">{{ __('Perubahan ini hanya berlaku untuk transaksi bulan ini. Nominal rutin bulan berikutnya tetap mengikuti pengaturan.') }}</div>
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <label for="recurring-base" class="form-label">{{ __('Nominal pokok bulan ini') }} <span class="req">*</span></label>
    <div class="input-group mb-3"><span class="input-group-text">Rp</span><input id="recurring-base" name="base_amount" type="number" step="0.01" min="1" max="9999999999999" class="form-control num" x-model="base" required></div>
    <h3 class="fs-6">{{ __('Tambahan bulan ini') }}</h3>
    <template x-for="(row, index) in rows" :key="row.key">
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2"><strong x-text="{{ Js::from(__('Tambahan')) }} + ' ' + (index + 1)"></strong><button type="button" class="btn btn-sm btn-danger-soft" @click="rows.splice(index, 1)">{{ __('Hapus tambahan') }}</button></div>
            <label :for="'addition-label-' + row.key" class="form-label">{{ __('Nama tambahan') }}</label>
            <input :id="'addition-label-' + row.key" :name="'additions[' + index + '][label]'" x-model="row.label" class="form-control mb-2" maxlength="150" placeholder="{{ __('Contoh: Lembur Housekeeping') }}" required>
            <label :for="'addition-amount-' + row.key" class="form-label">{{ __('Nominal tambahan') }}</label>
            <div class="input-group mb-2"><span class="input-group-text">Rp</span><input :id="'addition-amount-' + row.key" :name="'additions[' + index + '][amount]'" type="number" step="0.01" min="1" max="9999999999999" class="form-control num" x-model="row.amount" required></div>
            <label :for="'addition-note-' + row.key" class="form-label">{{ __('Catatan tambahan') }}</label>
            <textarea :id="'addition-note-' + row.key" :name="'additions[' + index + '][note]'" x-model="row.note" class="form-control" rows="2" maxlength="1000" placeholder="{{ __('Contoh: Lembur 5 hari × Rp50.000') }}"></textarea>
        </div>
    </template>
    <button type="button" class="btn btn-light mb-3" @click="rows.push({ key: nextKey++, label: '', amount: '', note: '' })" :disabled="rows.length >= 30"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah lembur / tambahan lain') }}</button>
    <div class="d-flex justify-content-between border-top pt-3 mb-3" aria-live="polite"><strong>{{ __('Total pengeluaran') }}</strong><strong class="num" x-text="new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(total)"></strong></div>
</div>
