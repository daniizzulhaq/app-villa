<x-layouts.app :title="__('Pratinjau Import')">
    @php $c = $preview['counts']; @endphp
    <x-ui.page-header :title="__('Pratinjau Import')" :subtitle="$fileName.' · '.__(':count baris dibaca', ['count' => count($preview['rows'])])" :back="route('imports.create')" :back-label="__('Ganti file')" />

    <div class="row g-3 mb-3">
        <div class="col-4"><x-ui.stat :label="__('Siap diimport')" :value="$c['ok']" icon="bi-check-circle" icon-tone="success" /></div>
        <div class="col-4"><x-ui.stat :label="__('Duplikat (dilewati)')" :value="$c['duplicate']" icon="bi-files" icon-tone="warning" /></div>
        <div class="col-4"><x-ui.stat :label="__('Error (dilewati)')" :value="$c['error']" icon="bi-x-circle" :icon-tone="$c['error'] ? 'danger' : 'success'" :tone="$c['error'] ? 'negative' : null" /></div>
    </div>

    @php $missing = array_diff(['payment_date', 'gross', 'booking_ref'], array_keys($preview['mapped'])); @endphp
    @if ($missing && ! (in_array('gross', $missing) && isset($preview['mapped']['amount'])))
        <div class="alert alert-warning d-flex gap-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div class="fs-sm">{{ __('Kolom tidak ditemukan:') }} <strong>{{ implode(', ', $missing) }}</strong>{{ __('. Kolom di file: :columns. Gunakan template bila perlu.', ['columns' => implode(', ', $preview['columns'])]) }}</div></div>
    @endif

    @if ($preview['prices'] ?? 0)
        <div class="alert alert-info d-flex gap-2"><i class="bi bi-tag" aria-hidden="true"></i>
            <div class="fs-sm">{{ __(':count reservasi akan diisi harga reservasinya (dari kolom gross). Harga yang diisi manual tidak ditimpa.', ['count' => $preview['prices']]) }}</div></div>
    @endif

    <x-ui.card flush x-data="{ show: 'all' }">
        <nav class="pm-tabs" aria-label="{{ __('Filter baris') }}">
            <a href="#" @click.prevent="show = 'all'" :class="show === 'all' && 'active'">{{ __('Semua') }} <span class="count">{{ count($preview['rows']) }}</span></a>
            <a href="#" @click.prevent="show = 'ok'" :class="show === 'ok' && 'active'">{{ __('Siap') }} <span class="count">{{ $c['ok'] }}</span></a>
            <a href="#" @click.prevent="show = 'duplicate'" :class="show === 'duplicate' && 'active'">{{ __('Duplikat') }} <span class="count">{{ $c['duplicate'] }}</span></a>
            <a href="#" @click.prevent="show = 'error'" :class="show === 'error' && 'active'">{{ __('Error') }} <span class="count">{{ $c['error'] }}</span></a>
        </nav>
        <div class="table-responsive" style="max-height: 60vh">
            <table class="table pm-table pm-table--stack fs-sm">
                <thead><tr><th>{{ __('Baris') }}</th><th>{{ __('Status') }}</th><th>{{ __('Villa') }}</th><th>{{ __('Platform') }}</th><th>{{ __('Kode') }}</th><th>{{ __('Tanggal') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Komisi') }}</th><th class="num">{{ __('Potongan') }}</th><th>{{ __('Reservasi') }}</th></tr></thead>
                <tbody>
                @foreach ($preview['rows'] as $r)
                    <tr x-show="show === 'all' || show === @js($r['status'])" style="{{ $r['status'] === 'duplicate' ? 'background: var(--pm-warning-soft)' : ($r['status'] === 'error' ? 'background: var(--pm-danger-soft)' : '') }}">
                        <td data-label="{{ __('Baris') }}">#{{ $r['line'] }}</td>
                        <td data-label="{{ __('Status') }}" class="stack-full">
                            @if ($r['status'] === 'ok') <x-ui.badge variant="success">{{ __('Siap') }}</x-ui.badge>
                            @elseif ($r['status'] === 'duplicate') <x-ui.badge variant="warning">{{ __('Duplikat') }}</x-ui.badge> <span class="fs-xs">{{ $r['dup_reason'] }}</span>
                            @else <x-ui.badge variant="danger">{{ __('Error') }}</x-ui.badge> <span class="fs-xs text-negative">{{ implode('; ', $r['errors']) }}</span>
                            @endif
                        </td>
                        <td data-label="{{ __('Villa') }}">{{ $r['villa'] ?: '—' }}</td>
                        <td data-label="{{ __('Platform') }}">{{ $r['platform'] ?: '—' }}</td>
                        <td data-label="{{ __('Kode') }}">{{ $r['booking_ref'] ?: '—' }}</td>
                        <td data-label="{{ __('Tanggal') }}">{{ $r['payment_date'] ? \Illuminate\Support\Carbon::parse($r['payment_date'])->translatedFormat('d M Y') : '—' }}</td>
                        <td data-label="{{ __('Gross') }}" class="num">{{ $r['gross_amount'] !== null ? \App\Support\Rupiah::format($r['gross_amount']) : '—' }}@if($r['is_net_of_platform'])<div class="cell-sub">{{ __('sudah net') }}</div>@endif</td>
                        <td data-label="{{ __('Komisi') }}" class="num">@rupiah($r['platform_fee'])</td>
                        <td data-label="{{ __('Potongan') }}" class="num">@rupiah($r['other_deduction'])</td>
                        <td data-label="{{ __('Reservasi') }}">
                            @if (! empty($r['booking_id']))
                                <a href="{{ route('bookings.show', $r['booking_id']) }}" target="_blank" rel="noopener">{{ $r['booking_label'] }}</a>
                                @if ($r['sets_price'])<div class="cell-sub text-success">{{ __('harga diisi') }}</div>@endif
                            @else
                                <span class="text-muted">{{ __('tidak ditemukan') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pm-form-actions">
            <a href="{{ route('imports.create') }}" class="btn btn-light">{{ __('Batal') }}</a>
            <form method="POST" action="{{ route('imports.store') }}" data-confirm="{{ __('Import :count pemasukan?', ['count' => $c['ok']]) }}" data-confirm-text="{{ __('Duplikat & baris error tidak akan disimpan. Management fee dihitung otomatis per villa.') }}" data-confirm-button="{{ __('Ya, import') }}">
                @csrf <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="btn btn-primary" @disabled(! $c['ok']) data-loading-text="{{ __('Mengimport…') }}"><i class="bi bi-check2-all" aria-hidden="true"></i> {{ __('Import :ok Baris', ['ok' => $c['ok']]) }}</button>
            </form>
        </div>
    </x-ui.card>
</x-layouts.app>
