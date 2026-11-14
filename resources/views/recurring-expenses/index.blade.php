<x-layouts.app :title="__('Pengeluaran Rutin')">
    <x-ui.page-header icon="bi-arrow-repeat" :title="__('Pengeluaran Rutin')" :subtitle="__('Atur sekali untuk gaji staff, iuran, pajak nominal tetap, dan kebutuhan bulanan villa.')">
        <x-slot:actions>
            <a href="{{ route('expenses.index') }}" class="btn btn-light">{{ __('Lihat pengeluaran') }}</a>
            <a href="{{ route('recurring-expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah pengeluaran rutin') }}</a>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="alert alert-info">{{ __('Gunakan Tambah bulan ini untuk lembur atau tambahan satu bulan. Pengeluaran tetap tercatat otomatis sesuai jadwal, dan Edit pengaturan mengatur pencatatan berikutnya.') }}</div>
    <x-ui.card flush>
        @if ($rules->isEmpty())
            <x-ui.empty-state :title="__('Belum ada pengeluaran rutin')" :text="__('Buat jadwal untuk Staff Housekeeping, Staff Pool Guy, Staff Gardener, Trash Fee, Banjar Fee, atau Tax dengan nominal tetap.')">
                <a href="{{ route('recurring-expenses.create') }}" class="btn btn-primary">{{ __('Tambah pengeluaran rutin') }}</a>
            </x-ui.empty-state>
        @else
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Keterangan') }}</th><th>{{ __('Villa') }}</th><th class="num">{{ __('Nominal pokok') }}</th><th>{{ __('Jadwal') }}</th><th>{{ __('Status') }}</th><th><span class="visually-hidden">{{ __('Aksi') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($rules as $rule)
                        <tr>
                            <td class="stack-full"><a href="{{ route('recurring-expenses.edit', $rule) }}" class="cell-title">{{ $rule->description }}</a><div class="cell-sub">{{ __($rule->category->name) }}</div></td>
                            <td data-label="{{ __('Villa') }}">{{ $rule->villa?->name ?? __('Villa dihapus') }}</td>
                            <td data-label="{{ __('Nominal pokok') }}" class="num fw-semibold">@rupiah($rule->amount)</td>
                            <td data-label="{{ __('Jadwal') }}">{{ __('Tanggal :day setiap bulan', ['day' => $rule->day_of_month]) }}<div class="cell-sub">{{ $rule->starts_on->translatedFormat('M Y') }}{{ $rule->ends_on ? ' – '.$rule->ends_on->translatedFormat('M Y') : '' }}</div></td>
                            <td data-label="{{ __('Status') }}">
                                @if (! $rule->is_active)<span class="badge text-bg-secondary">{{ __('Nonaktif') }}</span>
                                @elseif ($rule->ends_on && $rule->next_period->gt($rule->ends_on))<span class="badge text-bg-secondary">{{ __('Selesai') }}</span>
                                @elseif ($lockedPeriods->contains($rule->villa_id.':'.$rule->next_period->format('Y-m')))
                                    <span class="badge text-bg-warning">{{ __('Tertahan laporan Final') }}</span>
                                    <div class="cell-sub">{{ $rule->next_period->translatedFormat('M Y') }} · <a href="{{ route('reports.index', ['villa' => $rule->villa_id]) }}">{{ __('Buka laporan') }}</a></div>
                                @else<span class="badge text-bg-success">{{ __('Aktif') }}</span>
                                <div class="cell-sub">{{ __('Periode berikutnya') }}: {{ $rule->next_period->translatedFormat('M Y') }}</div>@endif
                            </td>
                            <td class="text-end stack-actions"><div class="d-flex flex-wrap justify-content-end gap-2">
                                <a href="{{ route('recurring-expenses.additions', $rule) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah bulan ini') }}</a>
                                <a href="{{ route('recurring-expenses.edit', $rule) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit pengaturan') }}</a>
                            </div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-ui.results-footer :items="$rules" />
    </x-ui.card>
</x-layouts.app>
