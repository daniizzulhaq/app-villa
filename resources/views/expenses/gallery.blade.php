<x-layouts.app :title="__('Galeri Nota')">
    <x-ui.page-header :title="__('Galeri Nota')" :subtitle="$month->translatedFormat('F Y').' · '.__(':photos foto dari :count pengeluaran', ['photos' => $receiptCount, 'count' => $expenses->count()])" :back="route('expenses.index')" :back-label="__('Pengeluaran')" />

    <x-ui.card flush>
        <form method="GET" class="pm-toolbar" data-no-lock>
            <select name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()" style="flex: 1 1 220px">
                @foreach ($villas as $id => $n)<option value="{{ $id }}" @selected($villaId === $id)>{{ $n }}</option>@endforeach
            </select>
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control" style="flex:0 1 200px" aria-label="{{ __('Bulan') }}" onchange="this.form.submit()">
            <a class="btn btn-light" href="{{ route('expenses.gallery', ['villa' => $villaId, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" aria-label="{{ __('Bulan sebelumnya') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <a class="btn btn-light" href="{{ route('expenses.gallery', ['villa' => $villaId, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" aria-label="{{ __('Bulan berikutnya') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </form>
        <div class="pm-card__body">
            @forelse ($expenses as $e)
                <div class="mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <a href="{{ route('expenses.show', $e) }}" class="fw-semibold text-ink text-decoration-none">{{ $e->expense_date->translatedFormat('d M') }} · {{ $e->description }}</a>
                        <span class="d-flex gap-2 align-items-center"><span class="num fw-semibold">@rupiah($e->amount)</span><x-ui.badge :status="$e->status" /></span>
                    </div>
                    @if ($e->receipts->isNotEmpty()) @include('expenses._receipts', ['receipts' => $e->receipts]) @else <p class="text-muted fs-sm">{{ __('Tanpa foto.') }}</p> @endif
                </div>
            @empty
                <x-ui.empty-state :title="__('Belum ada nota di bulan ini')" :text="__('Pilih villa atau bulan lain.')" />
            @endforelse
        </div>
    </x-ui.card>
</x-layouts.app>
