<x-layouts.app :title="__('Detail Pengeluaran')">
    @php $user = auth()->user(); @endphp
    <x-ui.page-header :title="\App\Support\Rupiah::format($expense->amount)" :subtitle="$expense->description.' · '.$expense->villa->name"
                      :back="$user->isStaff() ? route('staff.activity') : route('expenses.index')" :back-label="__('Kembali')">
        <x-slot:actions>
            @if ($user->isManager() && $expense->status === \App\Enums\ExpenseStatus::Pending)
                <a href="{{ route('reviews.show', $expense) }}" class="btn btn-light"><i class="bi bi-patch-check" aria-hidden="true"></i> {{ __('Periksa') }}</a>
            @endif
            @can('update', $expense)
                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> {{ $expense->status === \App\Enums\ExpenseStatus::Returned ? __('Perbaiki') : __('Edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($expense->status === \App\Enums\ExpenseStatus::Returned)
        <div class="alert alert-danger d-flex gap-2"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><div><strong>{{ __('Dikembalikan:') }}</strong> {{ $expense->return_reason }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7"><x-ui.card :title="__('Foto nota')" :subtitle="$expense->receipts->count().' file'">
            @if ($expense->receipts->isEmpty())<p class="text-muted mb-0">{{ __('Tidak ada foto nota.') }}</p>@else @include('expenses._receipts', ['receipts' => $expense->receipts]) @endif
        </x-ui.card></div>
        <div class="col-lg-5 d-grid gap-3 align-content-start">
            <x-ui.card :title="__('Data pengeluaran')">
                <div class="mb-3"><x-ui.badge :status="$expense->status" /></div>
                <dl class="pm-dl">
                    <dt>{{ __('Tanggal') }}</dt><dd>{{ $expense->expense_date->translatedFormat('l, d F Y') }}</dd>
                    <dt>{{ __('Kategori') }}</dt><dd><i class="bi {{ $expense->category->icon }}" aria-hidden="true"></i> {{ __($expense->category->name) }}</dd>
                    <dt>{{ __('Villa') }}</dt><dd>{{ $expense->villa->name }}</dd>
                    <dt>{{ __('Vendor') }}</dt><dd>{{ $expense->vendor ?: '—' }}</dd>
                    <dt>{{ __('Metode bayar') }}</dt><dd>{{ $expense->payment_method?->label() ?? '—' }}</dd>
                    <dt>{{ __('Diinput oleh') }}</dt><dd>{{ $expense->creator?->name ?? '—' }}, {{ $expense->created_at->translatedFormat('d M Y H:i') }}</dd>
                    @if ($expense->checked_at)<dt>{{ __('Diperiksa') }}</dt><dd>{{ $expense->checker?->name }}, {{ $expense->checked_at->translatedFormat('d M Y H:i') }}</dd>@endif
                    <dt>{{ __('Catatan') }}</dt><dd class="fw-normal" style="white-space:pre-line">{{ $expense->notes ?: '—' }}</dd>
                </dl>
            </x-ui.card>
            @can('delete', $expense)
                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" data-confirm="{{ __('Hapus pengeluaran ini?') }}"
                      data-confirm-text="{{ __('Total & laporan langsung diperbarui. Penghapusan tercatat di audit log.') }}" data-confirm-button="{{ __('Ya, hapus') }}" data-confirm-danger>
                    @csrf @method('DELETE')
                    <button class="btn btn-danger-soft w-100" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('Hapus pengeluaran') }}</button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
