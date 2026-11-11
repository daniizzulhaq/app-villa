<x-layouts.app :title="__('Review deleted income')">
    <x-ui.page-header :title="__('Review deleted income')" :subtitle="($booking->guest_name ?: $booking->booking_ref).' · '.$booking->villa->name.' · '.$booking->platform->name"
                      :back="route('bookings.show', $booking)" :back-label="__('Detail reservasi')" />

    <div class="alert alert-info">{{ __('Review the old entry and the Hostex amounts below. Restoring uses the same income record and retains its original management fee settings.') }}</div>
    <p class="fs-sm text-muted">{{ __('Amounts use the latest Hostex data saved in PropertyMe. Sync Hostex first if the reservation has changed.') }}
        @if ($booking->hostex_income_synced_at)<br>{{ __('Sinkron terakhir') }}: {{ $booking->hostex_income_synced_at->translatedFormat('d M Y H:i') }}@endif
    </p>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="fs-sm text-muted mb-3">{{ __('Kode booking') }}: <strong>{{ $booking->booking_ref }}</strong> · {{ __('Check-in') }} {{ $booking->check_in->translatedFormat('d M Y') }} · {{ __('Check-out') }} {{ $booking->check_out->translatedFormat('d M Y') }}</div>

    @forelse ($candidates as $income)
        <x-ui.card :title="__('Income entry #:id', ['id' => $income->id])" class="mb-3">
            <p class="fs-sm text-muted">{{ __('Deleted on :date', ['date' => $income->deleted_at->translatedFormat('d M Y H:i')]) }}</p>
            @if (isset($issues[$income->id]))
                <div class="alert alert-warning mb-0">{{ $issues[$income->id] }}</div>
            @else
                @php $projection = $projections[$income->id]; @endphp
                <div class="table-responsive"><table class="table pm-table pm-table--stack fs-sm">
                    <thead><tr><th>{{ __('Perhitungan') }}</th><th class="num">{{ __('Deleted entry') }}</th><th class="num">{{ __('After restoring from Hostex') }}</th></tr></thead>
                    <tbody>
                        <tr><td class="stack-full fw-semibold">{{ __('Recorded date') }}</td><td class="num" data-label="{{ __('Deleted entry') }}">{{ $income->payment_date->format('d/m/Y') }}</td><td class="num" data-label="{{ __('After restoring from Hostex') }}">{{ \Illuminate\Support\Carbon::parse($projection['payment_date'])->format('d/m/Y') }}</td></tr>
                        <tr><td class="stack-full fw-semibold">{{ __('Gross') }}</td><td class="num" data-label="{{ __('Deleted entry') }}">@rupiah($income->gross_amount)</td><td class="num" data-label="{{ __('After restoring from Hostex') }}">@rupiah($projection['gross_amount'])</td></tr>
                        <tr><td class="stack-full fw-semibold">{{ __('Komisi platform') }}</td><td class="num" data-label="{{ __('Deleted entry') }}">@rupiah($income->platform_fee)</td><td class="num" data-label="{{ __('After restoring from Hostex') }}">@rupiah($projection['platform_fee'])</td></tr>
                        <tr><td class="stack-full fw-semibold">{{ __('Net booking revenue') }}</td><td class="num" data-label="{{ __('Deleted entry') }}">@rupiah($income->net_revenue)</td><td class="num fw-semibold" data-label="{{ __('After restoring from Hostex') }}">@rupiah($projection['net_revenue'])</td></tr>
                        <tr><td class="stack-full fw-semibold">{{ __('Management fee') }}</td><td class="num" data-label="{{ __('Deleted entry') }}">@rupiah($income->fee_amount)</td><td class="num" data-label="{{ __('After restoring from Hostex') }}">@rupiah($projection['fee_amount'])<div class="cell-sub">{{ \App\Support\Rupiah::percent($projection['fee_percentage']) }} · {{ $projection['fee_basis']->label() }}</div></td></tr>
                    </tbody>
                </table></div>
                <form method="POST" action="{{ route('bookings.recover-income', $booking) }}">
                    @csrf
                    <input type="hidden" name="income_id" value="{{ $income->id }}">
                    <input type="hidden" name="review_token" value="{{ $tokens[$income->id] }}">
                    <button class="btn btn-primary" type="submit">{{ __('Restore and sync income') }}</button>
                </form>
            @endif
        </x-ui.card>
    @empty
        <x-ui.card><p class="mb-0 text-muted">{{ __('No matching deleted income was found. Sync Hostex and reopen this review.') }}</p></x-ui.card>
    @endforelse

    @if ($related->contains(fn ($income) => ! $income->trashed()))
        <x-ui.card :title="__('Daftar pemasukan')" class="mt-3">
            @foreach ($related->filter(fn ($income) => ! $income->trashed()) as $income)
                @can('view', $income)<a class="btn btn-light" href="{{ route('incomes.show', $income) }}">{{ __('Income entry #:id', ['id' => $income->id]) }}</a>@endcan
            @endforeach
        </x-ui.card>
    @endif
</x-layouts.app>
