<x-layouts.app :title="__('Import Payout')">
    <x-ui.page-header :title="__('Import Payout')" :subtitle="__('Unggah file payout CSV/Excel dari Airbnb, Booking.com, atau template PropertyMe. Data dicek dulu sebelum disimpan.')"
                      :back="route('incomes.index')" :back-label="__('Pemasukan')">
        <x-slot:actions>
            <a href="{{ route('imports.template') }}" class="btn btn-light"><i class="bi bi-download" aria-hidden="true"></i> {{ __('Unduh Template') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('imports.preview') }}" enctype="multipart/form-data" class="pm-card" data-validate novalidate>
                @csrf
                <section class="pm-form-section">
                    <h2 class="pm-form-section__title mb-3">{{ __('1. Pilih file') }}</h2>
                    <div class="mb-3" data-field>
                        <label for="f_file" class="form-label">{{ __('File payout') }}<span class="req" aria-hidden="true">*</span></label>
                        <input type="file" id="f_file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".csv,.xlsx,.xls,text/csv" required data-label="{{ __('File') }}">
                        @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('CSV / XLSX maks. 5 MB. Baris pertama harus berisi nama kolom.') }}</div>
                    </div>
                </section>
                <section class="pm-form-section">
                    <h2 class="pm-form-section__title mb-1">{{ __('2. Nilai default') }}</h2>
                    <p class="pm-form-section__desc mb-3">{{ __('Dipakai jika file tidak punya kolom villa / platform (mis. export Airbnb untuk satu listing).') }}</p>
                    <div class="row">
                        <div class="col-sm-6"><x-form.select name="villa_id" :label="__('Villa default')" :options="$villas" :placeholder="__('— Ambil dari kolom file —')" /></div>
                        <div class="col-sm-6"><x-form.select name="platform_id" :label="__('Platform default')" :options="$platforms" :placeholder="__('— Ambil dari kolom file —')" /></div>
                    </div>
                    <x-form.select name="date_format" :label="__('Format tanggal di file')" :options="['d/m/Y' => __('DD/MM/YYYY atau YYYY-MM-DD (Indonesia)'), 'm/d/Y' => __('MM/DD/YYYY (export Airbnb)')]" value="d/m/Y" required class="mb-0" />
                </section>
                <div class="pm-form-actions">
                    <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Membaca file…') }}"><i class="bi bi-eye" aria-hidden="true"></i> {{ __('Lihat Pratinjau') }}</button>
                </div>
            </form>
        </div>
        <div class="col-xl-5 d-grid gap-3 align-content-start">
            <x-ui.card :title="__('Kolom yang dikenali')">
                <dl class="pm-dl fs-sm mb-0">
                    <dt>{{ __('Villa') }}</dt><dd class="fw-normal">{{ __('villa, listing, property') }}</dd>
                    <dt>{{ __('Platform') }}</dt><dd class="fw-normal">{{ __('platform, sumber, channel') }}</dd>
                    <dt>{{ __('Kode booking') }}</dt><dd class="fw-normal">{{ __('kode_booking, confirmation code, reservation number') }}</dd>
                    <dt>{{ __('Tanggal bayar') }}</dt><dd class="fw-normal">{{ __('tanggal_bayar, date, payout date') }}</dd>
                    <dt>{{ __('Gross') }}</dt><dd class="fw-normal">{{ __('gross, gross earnings, total, price') }}</dd>
                    <dt>{{ __('Komisi') }}</dt><dd class="fw-normal">{{ __('komisi, service fee, commission') }}</dd>
                    <dt>{{ __('Potongan lain') }}</dt><dd class="fw-normal">{{ __('potongan_lain, tax') }}</dd>
                    <dt>{{ __('Check-in / out') }}</dt><dd class="fw-normal">{{ __('check-in, check-out, start date, nights — untuk mencocokkan reservasi tanpa kode booking (Booking.com)') }}</dd>
                    <dt>{{ __('Payout net') }}</dt><dd class="fw-normal">{{ __('amount, paid out — dipakai jika gross tidak ada (otomatis ditandai sudah net)') }}</dd>
                </dl>
            </x-ui.card>
            <x-ui.card :title="__('Riwayat import')" flush>
                @forelse ($batches as $b)
                    <div class="px-4 py-2 border-bottom fs-sm">
                        <div class="fw-semibold text-ink text-truncate">{{ $b->file_name }}</div>
                        <div class="text-muted">{{ __(':i · :name · :rows_imported masuk, :rows_duplicate duplikat, :rows_failed error', ['i' => $b->created_at->translatedFormat('d M Y H:i'), 'name' => $b->creator?->name, 'rows_imported' => $b->rows_imported, 'rows_duplicate' => $b->rows_duplicate, 'rows_failed' => $b->rows_failed]) }}</div>
                    </div>
                @empty
                    <p class="px-4 py-3 text-muted fs-sm mb-0">{{ __('Belum pernah import.') }}</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
