<x-layouts.app :title="auth()->user()->isOwner() ? __('Pengeluaran & Nota') : __('Pengeluaran')">
    @php $user = auth()->user(); @endphp
    <x-ui.page-header icon="bi-receipt" :title="$user->isOwner() ? __('Pengeluaran & Nota') : __('Pengeluaran')"
                      :subtitle="$user->isOwner() ? __('Pengeluaran operasional yang sudah diperiksa Manager, lengkap dengan bukti nota.') : __('Semua pengeluaran operasional villa beserta status pemeriksaan nota.')">
        <x-slot:actions>
            @if ($user->isManager())
                <a href="{{ route('recurring-expenses.index') }}" class="btn btn-light"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Pengeluaran Rutin') }}</a>
            @endif
            @unless ($user->isStaff())
                <a href="{{ route('expenses.gallery', array_filter(['villa' => $filters['villa'] ?? null])) }}" class="btn btn-light"><i class="bi bi-images" aria-hidden="true"></i> {{ __('Galeri Nota') }}</a>
            @endunless
            @can('create', \App\Models\Expense::class)
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-camera" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        <x-ui.filters :reset="route('expenses.index')" :advanced-keys="['category', 'from', 'to', 'min', 'max']">
            @if(!$user->isOwner() && request()->filled('status'))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <div class="pm-filter-field pm-filter-field--search"><label for="q">{{ __('Cari') }}</label><div class="pm-filter-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari keterangan atau vendor…') }}"></div></div>
            <x-form.select name="villa" :label="__('Villa')" :options="$villas" :value="$filters['villa'] ?? null" :placeholder="__('Semua villa')" class="pm-filter-field" />
            <x-slot:advanced>
                <x-form.select name="category" :label="__('Kategori')" :options="$categories->map(fn ($name) => __($name))" :value="$filters['category'] ?? null" :placeholder="__('Semua kategori')" class="pm-filter-field" />
                <x-form.input type="date" name="from" :label="__('Dari tanggal')" :value="$filters['from'] ?? null" class="pm-filter-field" />
                <x-form.input type="date" name="to" :label="__('Sampai tanggal')" :value="$filters['to'] ?? null" class="pm-filter-field" />
                <x-form.input type="number" name="min" :label="__('Nominal minimum')" :value="$filters['min'] ?? null" :placeholder="__('Min Rp')" min="0" inputmode="numeric" class="pm-filter-field" />
                <x-form.input type="number" name="max" :label="__('Nominal maksimum')" :value="$filters['max'] ?? null" :placeholder="__('Maks Rp')" min="0" inputmode="numeric" class="pm-filter-field" />
            </x-slot:advanced>
        </x-ui.filters>
        @unless($user->isOwner())<x-ui.status-tabs :statuses="\App\Enums\ExpenseStatus::cases()" />@endunless
        <div class="pm-list-heading"><span><i class="bi bi-receipt" aria-hidden="true"></i>{{ __(':total pengeluaran', ['total' => $expenses->total()]) }}</span><span><small>{{ __('Total sesuai filter') }}</small><strong class="num text-ink">@rupiah($total)</strong></span></div>

        @if ($expenses->isEmpty())
            <x-ui.empty-state :title="array_filter($filters) ? __('Tidak ada hasil yang cocok') : __('Belum ada pengeluaran')" :text="array_filter($filters) ? __('Tidak ada yang cocok dengan filter.') : __('Pengeluaran yang diunggah Staff akan muncul di sini.')">
                @if(array_filter($filters))<a href="{{ route('expenses.index') }}" class="btn btn-light">{{ __('Reset filter') }}</a>@endif
                @can('create', \App\Models\Expense::class)
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-camera" aria-hidden="true"></i> {{ __('Upload Nota') }}</a>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Tanggal') }}</th><th>{{ __('Keterangan') }}</th><th>{{ __('Villa') }}</th><th>{{ __('Kategori') }}</th><th class="num">{{ __('Nominal') }}</th><th>{{ __('Status') }}</th><th>{{ __('Nota') }}</th><th><span class="visually-hidden">{{ __('Aksi') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($expenses as $e)
                        <tr>
                            <td data-label="{{ __('Tanggal') }}" class="text-nowrap">{{ $e->expense_date->translatedFormat('d M Y') }}</td>
                            <td class="stack-full"><a href="{{ route('expenses.show', $e) }}" class="cell-title text-decoration-none">{{ $e->description }}</a>
                                @if ($e->recurring_expense_id)<span class="badge text-bg-light"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Otomatis') }}</span>@endif
                                <div class="cell-sub">{{ __(':vendor · oleh :name', ['vendor' => $e->vendor ?: '—', 'name' => $e->creator?->name ?? '—']) }}</div></td>
                            <td data-label="{{ __('Villa') }}">{{ $e->villa?->name }}</td>
                            <td data-label="{{ __('Kategori') }}"><i class="bi {{ $e->category->icon }} text-muted" aria-hidden="true"></i> {{ __($e->category->name) }}</td>
                            <td data-label="{{ __('Nominal') }}" class="num fw-semibold text-ink">@rupiah($e->amount)</td>
                            <td data-label="{{ __('Status') }}"><x-ui.badge :status="$e->status" /></td>
                            <td data-label="{{ __('Nota') }}"><span class="fs-sm"><i class="bi bi-image" aria-hidden="true"></i> {{ $e->receipts->count() }}</span></td>
                            <td class="text-end stack-actions"><a href="{{ route('expenses.show', $e) }}" class="btn btn-sm btn-light">{{ __('Detail') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

        @endif
        <x-ui.results-footer :items="$expenses" />
    </x-ui.card>
</x-layouts.app>
