<x-layouts.app :title="$rule->exists ? __('Edit Pengeluaran Rutin') : __('Tambah Pengeluaran Rutin')">
    <x-ui.page-header :title="$rule->exists ? __('Edit Pengeluaran Rutin') : __('Tambah Pengeluaran Rutin')" :back="route('recurring-expenses.index')" :back-label="__('Kembali')" />
    <div class="row justify-content-center"><div class="col-lg-8">
        <div class="alert alert-info">{{ __('Tanggal 29–31 mengikuti hari terakhir jika bulan lebih pendek. Pengeluaran yang sudah tercatat bisa diedit terpisah, termasuk tambahan lembur bulan tersebut.') }}</div>
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <x-ui.card>
            <form method="POST" action="{{ $rule->exists ? route('recurring-expenses.update', $rule) : route('recurring-expenses.store') }}" data-validate>
                @csrf
                @if ($rule->exists) @method('PUT') @endif
                <x-form.select name="villa_id" :label="__('Villa')" :options="$villas" :value="$rule->villa_id" :placeholder="__('Pilih villa')" required />
                <x-form.input name="description" :label="__('Nama pengeluaran')" :value="$rule->description" :placeholder="__('Contoh: Staff Housekeeping')" maxlength="255" required />
                <x-form.select name="category_id" :label="__('Kategori')" :options="$categories" :value="$rule->category_id" :placeholder="__('Pilih kategori')" required />
                <x-form.money name="amount" :label="__('Nominal pokok per bulan')" :value="$rule->amount" required />
                <x-form.input type="number" name="day_of_month" :label="__('Tanggal pencatatan setiap bulan')" :value="$rule->day_of_month" min="1" max="31" required />
                <div class="row">
                    <div class="col-sm-6">
                        @if ($rule->exists)
                            <div class="mb-3"><span class="form-label d-block">{{ __('Bulan mulai') }}</span><p>{{ $rule->starts_on->translatedFormat('F Y') }}</p></div>
                        @else
                            <x-form.input type="month" name="start_month" :label="__('Bulan mulai')" :value="$rule->starts_on->format('Y-m')" min="2000-01" required :help="__('Jika sudah jatuh tempo, pengeluaran langsung dibuat saat disimpan.')" />
                        @endif
                    </div>
                    <div class="col-sm-6"><x-form.input type="month" name="end_month" :label="__('Bulan berakhir (opsional)')" :value="$rule->ends_on?->format('Y-m')" :min="$rule->exists ? $rule->starts_on->format('Y-m') : '2000-01'" /></div>
                </div>
                <x-form.select name="is_active" :label="__('Status jadwal')" :options="[1 => __('Aktif'), 0 => __('Nonaktif')]" :value="(int) $rule->is_active" :help="__('Aktifkan kembali untuk melanjutkan dari bulan berjalan. Bulan selama nonaktif tidak dibuat ulang.')" required />
                <x-form.input name="vendor" :label="__('Penerima / vendor')" :value="$rule->vendor" maxlength="150" />
                <x-form.select name="payment_method" :label="__('Metode bayar')" :options="$methods" :value="$rule->payment_method" placeholder="—" />
                <x-form.textarea name="notes" :label="__('Catatan rutin')" :value="$rule->notes" rows="3" maxlength="3000" />
                <div class="pm-form-actions"><a href="{{ route('recurring-expenses.index') }}" class="btn btn-light">{{ __('Batal') }}</a><button class="btn btn-primary" type="submit">{{ __('Simpan pengaturan') }}</button></div>
            </form>
        </x-ui.card>
        @if ($rule->exists)
            <form method="POST" action="{{ route('recurring-expenses.destroy', $rule) }}" class="mt-3" data-confirm="{{ __('Hapus jadwal rutin ini?') }}" data-confirm-text="{{ __('Pencatatan berikutnya dihentikan. Transaksi yang sudah tercatat tetap tersimpan.') }}" data-confirm-danger>
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger-soft">{{ __('Hapus jadwal rutin') }}</button>
            </form>
        @endif
    </div></div>
</x-layouts.app>
