<x-layouts.app :title="__('Detail Pemasukan')">
    <x-ui.page-header :title="\App\Support\Rupiah::format($income->gross_amount)" :subtitle="$income->villa->name.' · '.$income->platform->name.' · '.$income->payment_date->translatedFormat('d F Y')"
                      :back="route('incomes.index')" :back-label="__('Pemasukan')">
        <x-slot:actions>
            @can('update', $income)
                <a href="{{ route('incomes.edit', $income) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($income->isFromHostex())
        <div class="alert alert-info fs-sm">{{ __('Hostex entries are booking revenue. Their recorded date follows :basis; payouts are not verified.', ['basis' => $income->hostex_date_basis === 'check_out' ? __('Check-out') : __('Check-in')]) }} {{ __('Automatic Hostex income is managed by synchronization.') }}</div>
    @endif

    @if ($locked)
        <div class="alert alert-info d-flex gap-2"><i class="bi bi-lock" aria-hidden="true"></i><div>{{ __('Laporan bulan ini sudah') }} <strong>{{ __('Final') }}</strong>{{ __('. Pemasukan terkunci — Manager perlu membuka kunci laporan untuk mengubahnya.') }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <x-ui.card :title="__('Perhitungan')">
                <div class="table-responsive">
                <table class="pm-ledger">
                    <tr><td>{{ __('Gross booking income') }}</td><td>@rupiah($income->gross_amount)</td></tr>
                    <tr class="sub minus"><td>{{ __('Komisi platform :lagi', ['lagi' => $income->is_net_of_platform ? __('(sudah termasuk, tidak dipotong lagi)') : '']) }}</td><td>@rupiah($income->is_net_of_platform ? 0 : $income->platform_fee)</td></tr>
                    <tr class="sub minus"><td>{{ __('Potongan lain') }}</td><td>@rupiah($income->other_deduction)</td></tr>
                    <tr><td><strong>{{ __('Net booking revenue') }}</strong></td><td>@rupiah($income->net_revenue)</td></tr>
                    <tr class="sub minus"><td>{{ __('Management fee :fee_percentage ×', ['fee_percentage' => \App\Support\Rupiah::percent($income->fee_percentage)]) }} @rupiah($income->fee_base_amount)</td><td>@rupiah($income->fee_amount)</td></tr>
                    <tr class="total"><td>{{ __('Revenue after fee') }}</td><td>@rupiah(\Brick\Math\BigDecimal::of($income->net_revenue)->minus($income->fee_amount))</td></tr>
                </table>
                </div>
                <p class="fs-sm text-muted mt-3 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ __('Basis fee: :label. Persentase & basis disimpan sebagai snapshot saat transaksi dicatat.', ['label' => $income->fee_basis->label()]) }}</p>
            </x-ui.card>
        </div>
        <div class="col-lg-6 d-grid gap-3 align-content-start">
            <x-ui.card :title="__('Info')">
                <dl class="pm-dl">
                    <dt>{{ __('Reservasi') }}</dt>
                    <dd>@if ($income->booking)<a href="{{ route('bookings.show', $income->booking) }}">{{ $income->booking->guest_name ?: __('Booking #').$income->booking->id }}</a> · {{ $income->booking->check_in->translatedFormat('d M') }}–{{ $income->booking->check_out->translatedFormat('d M Y') }}@else — @endif</dd>
                    <dt>{{ __('Kode booking') }}</dt><dd>{{ $income->booking_ref ?: '—' }}</dd>
                    <dt>{{ __('Dicatat oleh') }}</dt><dd>{{ $income->isFromHostex() ? __('Automatic Hostex income') : ($income->creator?->name ?? ($income->import_batch_id ? __('Import file') : '—')) }}, {{ $income->created_at->translatedFormat('d M Y H:i') }}</dd>
                    <dt>{{ __('Catatan') }}</dt><dd class="fw-normal" style="white-space:pre-line">{{ $income->notes ?: '—' }}</dd>
                </dl>
            </x-ui.card>
            <x-ui.card :title="__('Bukti bayar')">
                @forelse ($income->attachments as $a)
                    <a href="{{ route('files.income', $a) }}" target="_blank" rel="noopener" class="d-flex align-items-center gap-2 py-2 text-decoration-none">
                        <i class="bi {{ str_ends_with($a->path, '.pdf') ? 'bi-file-earmark-pdf' : 'bi-image' }} fs-5" aria-hidden="true"></i> {{ $a->original_name }}
                        <span class="fs-xs text-muted ms-auto">{{ __(':size KB', ['size' => number_format($a->size / 1024, 0, ',', '.')]) }}</span>
                    </a>
                @empty
                    <p class="text-muted fs-sm mb-0">{{ __('Tidak ada bukti bayar.') }}</p>
                @endforelse
            </x-ui.card>
            @can('delete', $income)
                <form method="POST" action="{{ route('incomes.destroy', $income) }}" data-confirm="{{ __('Hapus pemasukan ini?') }}"
                      data-confirm-text="{{ __('Total, grafik, dan laporan akan langsung diperbarui. Penghapusan tercatat di audit log.') }}" data-confirm-button="{{ __('Ya, hapus') }}" data-confirm-danger>
                    @csrf @method('DELETE')
                    <button class="btn btn-danger-soft w-100" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus pemasukan') }}</button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
