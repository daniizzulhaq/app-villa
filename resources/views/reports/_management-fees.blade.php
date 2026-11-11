<section id="management-fees" aria-labelledby="management-fees-title" class="mb-3" style="scroll-margin-top: 100px">
    <x-ui.card flush>
        <div class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 id="management-fees-title" class="h6 mb-1">{{ __('Management fee breakdown') }}</h2>
                <p class="fs-sm text-muted mb-0">{{ __('Fees per income entry, using the percentage and calculation basis saved with each transaction.') }}</p>
            </div>
            <div class="num fw-semibold">@rupiah($s->managementFee)</div>
        </div>
        <div class="table-responsive">
            <table class="table pm-table pm-table--stack fs-sm mb-0">
                <thead><tr>
                    <th>{{ __('Recorded date') }}</th><th>{{ __('Tamu / kode') }}</th><th>{{ __('Villa') }} / {{ __('Sumber') }}</th>
                    <th class="num">{{ __('Basis fee (Rp)') }}</th><th class="num">{{ __('Fee %') }}</th><th class="num">{{ __('Management fee') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($incomes as $income)
                    <tr>
                        <td data-label="{{ __('Recorded date') }}">{{ $income->payment_date->translatedFormat('d M Y') }}</td>
                        <td class="stack-full" data-label="{{ __('Tamu / kode') }}">
                            <a href="{{ route('incomes.show', $income) }}" class="fw-semibold">{{ $income->booking?->guest_name ?: __('Income without a linked reservation') }}</a>
                            <div class="cell-sub">{{ $income->booking_ref ?: ($income->booking?->booking_ref ?: '#'.$income->id) }}</div>
                            @if ($income->booking)
                                <div class="cell-sub">{{ $income->booking->check_in->translatedFormat('d M Y') }} – {{ $income->booking->check_out->translatedFormat('d M Y') }}</div>
                            @endif
                        </td>
                        <td data-label="{{ __('Villa') }} / {{ __('Sumber') }}">{{ $income->villa->name }}<div class="cell-sub">{{ $income->platform->name }}</div></td>
                        <td class="num" data-label="{{ __('Basis fee (Rp)') }}">@rupiah($income->fee_base_amount)<div class="cell-sub">{{ $income->fee_basis->label() }}</div></td>
                        <td class="num" data-label="{{ __('Fee %') }}">{{ \App\Support\Rupiah::percent($income->fee_percentage) }}</td>
                        <td class="num fw-semibold" data-label="{{ __('Management fee') }}">@rupiah($income->fee_amount)</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ __('Tidak ada pemasukan.') }}</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr>
                    <th colspan="5">{{ __('Total management fee') }}</th>
                    <td class="num fw-semibold" data-label="{{ __('Total management fee') }}">@rupiah($s->managementFee)</td>
                </tr></tfoot>
            </table>
        </div>
    </x-ui.card>
</section>
