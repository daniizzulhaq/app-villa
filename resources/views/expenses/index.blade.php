<x-layouts.app :title="auth()->user()->isOwner() ? __('Pengeluaran & Nota') : __('Pengeluaran')">
    @php $user = auth()->user(); @endphp
    <x-ui.page-header :title="$user->isOwner() ? __('Pengeluaran & Nota') : __('Pengeluaran')"
                      :subtitle="$user->isOwner() ? __('Pengeluaran operasional yang sudah diperiksa Manager, lengkap dengan bukti nota.') : __('Semua pengeluaran operasional villa beserta status pemeriksaan nota.')">
        <x-slot:actions>
            @unless ($user->isStaff())
                <a href="{{ route('expenses.gallery', array_filter(['villa' => $filters['villa'] ?? null])) }}" class="btn btn-light"><i class="bi bi-images" aria-hidden="true"></i> {{ __('Galeri Nota') }}</a>
            @endunless
            @can('create', \App\Models\Expense::class)
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-camera" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        <form method="GET" class="pm-toolbar" role="search" data-no-lock>
            <div class="pm-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="q">{{ __('Cari') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari keterangan atau vendor…') }}">
            </div>
            <select name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()">
                <option value="">{{ __('Semua villa') }}</option>
                @foreach ($villas as $id => $n)<option value="{{ $id }}" @selected((string) ($filters['villa'] ?? '') === (string) $id)>{{ $n }}</option>@endforeach
            </select>
            <select name="category" class="form-select" aria-label="{{ __('Kategori') }}" onchange="this.form.submit()">
                <option value="">{{ __('Semua kategori') }}</option>
                @foreach ($categories as $id => $n)<option value="{{ $id }}" @selected((string) ($filters['category'] ?? '') === (string) $id)>{{ $n }}</option>@endforeach
            </select>
            @unless ($user->isOwner())
                <select name="status" class="form-select" aria-label="{{ __('Status') }}" onchange="this.form.submit()">
                    <option value="">{{ __('Semua status') }}</option>
                    @foreach (\App\Enums\ExpenseStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
                </select>
            @endunless
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" style="flex:0 1 150px" aria-label="{{ __('Dari tanggal') }}">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" style="flex:0 1 150px" aria-label="{{ __('Sampai tanggal') }}">
            <input type="number" name="min" value="{{ $filters['min'] ?? '' }}" class="form-control" style="flex:0 1 130px" placeholder="{{ __('Min Rp') }}" aria-label="{{ __('Nominal minimum') }}" inputmode="numeric">
            <input type="number" name="max" value="{{ $filters['max'] ?? '' }}" class="form-control" style="flex:0 1 130px" placeholder="{{ __('Maks Rp') }}" aria-label="{{ __('Nominal maksimum') }}" inputmode="numeric">
            <button type="submit" class="btn btn-light"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
            @if (array_filter($filters))<a href="{{ route('expenses.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>@endif
        </form>

        @if ($expenses->isEmpty())
            <x-ui.empty-state :title="__('Belum ada pengeluaran')" :text="array_filter($filters) ? __('Tidak ada yang cocok dengan filter.') : __('Pengeluaran yang diunggah Staff akan muncul di sini.')">
                @can('create', \App\Models\Expense::class)
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-camera" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="px-4 py-2 border-bottom d-flex justify-content-between fs-sm bg-light">
                <span class="text-muted">{{ __(':total pengeluaran', ['total' => $expenses->total()]) }}</span>
                <span>{{ __('Total:') }} <strong class="text-ink num">@rupiah($total)</strong></span>
            </div>
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Tanggal') }}</th><th>{{ __('Keterangan') }}</th><th>{{ __('Villa') }}</th><th>{{ __('Kategori') }}</th><th class="num">{{ __('Nominal') }}</th><th>{{ __('Status') }}</th><th>{{ __('Nota') }}</th></tr></thead>
                    <tbody>
                    @foreach ($expenses as $e)
                        <tr>
                            <td data-label="{{ __('Tanggal') }}" class="text-nowrap">{{ $e->expense_date->translatedFormat('d M Y') }}</td>
                            <td class="stack-full"><a href="{{ route('expenses.show', $e) }}" class="cell-title text-decoration-none">{{ $e->description }}</a>
                                <div class="cell-sub">{{ __(':vendor · oleh :name', ['vendor' => $e->vendor ?: '—', 'name' => $e->creator?->name ?? '—']) }}</div></td>
                            <td data-label="{{ __('Villa') }}">{{ $e->villa?->name }}</td>
                            <td data-label="{{ __('Kategori') }}"><i class="bi {{ $e->category->icon }} text-muted" aria-hidden="true"></i> {{ __($e->category->name) }}</td>
                            <td data-label="{{ __('Nominal') }}" class="num fw-semibold text-ink">@rupiah($e->amount)</td>
                            <td data-label="{{ __('Status') }}"><x-ui.badge :status="$e->status" /></td>
                            <td data-label="{{ __('Nota') }}"><span class="fs-sm"><i class="bi bi-image" aria-hidden="true"></i> {{ $e->receipts->count() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())<div class="p-3 border-top">{{ $expenses->onEachSide(1)->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
