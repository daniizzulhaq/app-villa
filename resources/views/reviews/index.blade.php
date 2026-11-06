<x-layouts.app :title="__('Pemeriksaan Nota')">
    <x-ui.page-header icon="bi-patch-check" :title="__('Pemeriksaan Nota')" :subtitle="__(':count nota menunggu pemeriksaan. Hanya nota Valid yang masuk perhitungan keuangan.', ['count' => $queue->total()])">
        <x-slot:actions>
            @if ($queue->isNotEmpty())
                <a href="{{ route('reviews.show', $queue->first()) }}" class="btn btn-primary"><i class="bi bi-play-fill" aria-hidden="true"></i> {{ __('Mulai Periksa') }}</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-ui.card flush>
                <form method="GET" class="pm-toolbar" data-no-lock>
                    <select name="villa" class="form-select" aria-label="{{ __('Villa') }}" onchange="this.form.submit()" style="flex: 1 1 220px">
                        <option value="">{{ __('Semua villa') }}</option>
                        @foreach ($villas as $id => $n)<option value="{{ $id }}" @selected($villaId === $id)>{{ $n }}</option>@endforeach
                    </select>
                </form>

                @if ($queue->isEmpty())
                    <x-ui.empty-state :title="__('Semua nota sudah diperiksa')" :text="__('Nota baru dari Staff akan muncul di sini dan Anda akan mendapat notifikasi.')" />
                @else
                    <form method="POST" action="{{ route('reviews.bulk') }}" x-data="{ ids: [], get all() { return this.ids.length === {{ $queue->count() }} } }"
                          data-confirm="{{ __('Tandai nota terpilih sebagai Valid?') }}" data-confirm-text="{{ __('Pastikan foto & nominal sudah dicek. Nota Valid langsung masuk laporan.') }}" data-confirm-button="{{ __('Ya, tandai Valid') }}">
                        @csrf
                        <div class="pm-review-selection">
                            <label class="d-flex align-items-center gap-2 mb-0 fs-sm fw-semibold">
                                <input type="checkbox" class="form-check-input mt-0" :checked="all" x-effect="$el.indeterminate = ids.length > 0 && !all"
                                       @change="ids = $event.target.checked ? @js($queue->pluck('id')->map(fn ($i) => (string) $i)) : []"> {{ __('Pilih semua') }}
                            </label>
                            <button type="submit" class="btn btn-sm btn-primary ms-auto" :disabled="!ids.length">
                                <i class="bi bi-check2-all" aria-hidden="true"></i> {{ __('Tandai Valid (') }}<span x-text="ids.length"></span>)
                            </button>
                        </div>
                        @foreach ($queue as $e)
                            <div class="pm-review-row">
                                <input type="checkbox" name="ids[]" value="{{ $e->id }}" x-model="ids" class="form-check-input mt-0 flex-shrink-0" aria-label="{{ __('Pilih nota :description', ['description' => $e->description]) }}">
                                <a href="{{ route('reviews.show', $e) }}">
                                    @php $first = $e->receipts->first(); @endphp
                                    @if ($first && ! str_ends_with($first->path, '.pdf'))
                                        <img src="{{ route('files.receipt', $first) }}" alt="" class="pm-villa-thumb" loading="lazy">
                                    @else
                                        <span class="pm-villa-thumb" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                                    @endif
                                    <span class="min-w-0 flex-grow-1">
                                        <span class="d-block fw-semibold text-ink text-truncate">{{ $e->description }}</span>
                                        <span class="pm-review-row__meta">{{ $e->villa->name }} · {{ __($e->category->name) }}<br>{{ $e->creator?->name }} · {{ $e->expense_date->translatedFormat('d M') }}</span>
                                    </span>
                                    <span class="text-end flex-shrink-0">
                                        <span class="pm-review-row__amount num">@rupiah($e->amount)</span>
                                        <span class="d-block fs-xs text-muted">{{ __(':count foto · :diffforhumans', ['count' => $e->receipts->count(), 'diffforhumans' => $e->created_at->diffForHumans()]) }}</span>
                                        <span class="pm-review-row__action">{{ __('Periksa Nota') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </form>
                @endif
                <x-ui.results-footer :items="$queue" />
            </x-ui.card>
        </div>
        <div class="col-xl-4">
            <x-ui.card :title="__('Baru diperiksa')" flush>
                @forelse ($recent as $r)
                    <a href="{{ route('expenses.show', $r) }}" class="d-flex justify-content-between gap-2 px-4 py-3 border-bottom text-decoration-none">
                        <span class="min-w-0"><span class="d-block text-ink fw-semibold text-truncate">{{ $r->description }}</span>
                            <span class="d-block fs-xs text-muted">{{ $r->villa?->name }} · {{ $r->checked_at->diffForHumans() }}</span></span>
                        <x-ui.badge :status="$r->status" />
                    </a>
                @empty
                    <p class="p-4 text-muted fs-sm mb-0">{{ __('Belum ada.') }}</p>
                @endforelse
            </x-ui.card>
            <aside class="pm-review-tip">
                <h2>{{ __('Sebelum menandai Valid') }}</h2>
                <ul>
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ __('Foto nota terbaca') }}</li>
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ __('Nominal sesuai nota') }}</li>
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ __('Kategori & tanggal sesuai') }}</li>
                </ul>
            </aside>
        </div>
    </div>
</x-layouts.app>
