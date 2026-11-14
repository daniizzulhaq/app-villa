<x-layouts.app :title="__('Tambahan pengeluaran bulanan')">
    <x-ui.page-header :title="__('Tambahan pengeluaran bulanan')" :subtitle="$rule->description.' · '.($rule->villa?->name ?? __('Villa dihapus'))" :back="route('recurring-expenses.index')" :back-label="__('Kembali')" />
    <div class="row g-3 justify-content-center">
        <div class="col-lg-8">
            <x-ui.card>
                <form method="GET" action="{{ route('recurring-expenses.additions', $rule) }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <x-form.input type="month" name="month" :label="__('Bulan tambahan')" :value="$month" required class="flex-grow-1" />
                    <button type="submit" class="btn btn-light mb-3">{{ __('Tampilkan bulan') }}</button>
                </form>
                <h2 class="fs-5">{{ $period->translatedFormat('F Y') }}</h2>
                <p class="text-muted">{{ __('Tambahan hanya berlaku pada bulan yang dipilih. Gaji pokok dan pengaturan bulan berikutnya tetap.') }}</p>
                @if ($expense?->trashed())
                    <div class="alert alert-warning">{{ __('Transaksi bulan ini sudah dihapus. Tambahan tidak dapat dimasukkan.') }}</div>
                @elseif ($locked)
                    <div class="alert alert-warning">{{ __('Laporan bulan ini sudah Final dan terkunci. Buka kunci di menu Laporan untuk mengubah data.') }}</div>
                @elseif ($outsideSchedule)
                    <div class="alert alert-warning">{{ __('Bulan ini tidak termasuk jadwal rutin yang aktif. Pilih bulan lain atau periksa pengaturan.') }}</div>
                @elseif (! $expense)
                    <div class="alert alert-info">{{ __('Tambahan disimpan dulu dan digabung otomatis saat pengeluaran tercatat pada :date. Belum masuk laporan sebelum tanggal pencatatan.', ['date' => $due->translatedFormat('d F Y')]) }}</div>
                @else
                    <div class="alert alert-info">{{ __('Pengeluaran bulan ini sudah tercatat. Tambahan langsung memperbarui total bulan ini.') }}</div>
                @endif
                <dl class="pm-dl">
                    <dt>{{ __('Nominal pokok') }}</dt><dd>@rupiah($base)</dd>
                    @foreach ($additions as $addition)
                        <dt>{{ $addition['label'] }}</dt><dd>@rupiah($addition['amount'])
                            @if (!empty($addition['note']))<div class="fw-normal fs-sm" style="white-space:pre-line">{{ $addition['note'] }}</div>@endif
                        </dd>
                    @endforeach
                    <dt>{{ $expense ? __('Total pengeluaran') : __('Total saat pencatatan') }}</dt><dd>@rupiah($total)</dd>
                </dl>
                @if ($expense && ! $expense->trashed())
                    <a href="{{ route('expenses.show', $expense) }}" class="btn btn-light">{{ __('Lihat transaksi bulan ini') }}</a>
                @endif
            </x-ui.card>

            @if ($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
            @unless ($locked || $outsideSchedule || $expense?->trashed())
                <div class="mt-3"><x-ui.card :title="__('Tambah lembur / tambahan lain')">
                    <form method="POST" action="{{ route('recurring-expenses.additions.store', $rule) }}" data-validate>
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <x-form.input name="label" :label="__('Nama tambahan')" :value="__('Lembur')" maxlength="150" required />
                        <x-form.money name="amount" :label="__('Nominal tambahan')" required />
                        <x-form.textarea name="note" :label="__('Catatan tambahan')" :placeholder="__('Contoh: Lembur bulan ini Rp200.000')" rows="3" maxlength="1000" />
                        <div class="pm-form-actions"><a href="{{ route('recurring-expenses.index') }}" class="btn btn-light">{{ __('Kembali') }}</a><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Simpan tambahan bulan ini') }}</button></div>
                    </form>
                </x-ui.card></div>
                @if (! $expense && $pending->isNotEmpty())
                    <div class="mt-3"><x-ui.card :title="__('Tambahan menunggu pencatatan')">
                        @foreach ($pending as $item)
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                                <div><strong>{{ $item->label }}</strong><div class="text-muted fs-sm">@rupiah($item->amount)</div></div>
                                <form method="POST" action="{{ route('recurring-expenses.additions.destroy', [$rule, $item]) }}" data-confirm="{{ __('Hapus tambahan ini?') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger-soft">{{ __('Hapus tambahan') }}</button>
                                </form>
                            </div>
                        @endforeach
                    </x-ui.card></div>
                @endif
            @endunless
        </div>
    </div>
</x-layouts.app>
