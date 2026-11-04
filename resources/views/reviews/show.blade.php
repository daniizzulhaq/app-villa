<x-layouts.app :title="__('Periksa Nota')">
    @php $images = $expense->receipts->reject(fn ($r) => str_ends_with($r->path, '.pdf'))->values(); @endphp
    <x-ui.page-header :title="__('Periksa Nota')" :subtitle="$position ? __('Nota :position dari :total yang menunggu', ['position' => $position, 'total' => $pendingTotal]) : __('Status: :status', ['status' => $expense->status->label()])"
                      :back="route('reviews.index')" :back-label="__('Antrean nota')">
        <x-slot:actions>
            @if ($nextId && $nextId !== $expense->id)
                <a href="{{ route('reviews.show', $nextId) }}" class="btn btn-light">{{ __('Lewati') }} <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="pm-review">
        {{-- Kiri: foto --}}
        <div x-data="{ i: 0, urls: @js($images->map(fn ($r) => route('files.receipt', $r))) }">
            <div class="pm-review__photo">
                <template x-if="urls.length"><img :src="urls[i]" alt="{{ __('Foto nota') }}"></template>
                <template x-if="!urls.length"><div class="text-center text-white-50 p-5"><i class="bi bi-image fs-1 d-block mb-2" aria-hidden="true"></i>{{ __('Tidak ada foto nota') }}</div></template>
                <a x-show="urls.length" :href="urls[i]" target="_blank" rel="noopener" class="btn btn-light btn-sm position-absolute" style="top:12px; right:12px"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i> {{ __('Buka asli') }}</a>
            </div>
            <div class="pm-review__thumbs" x-show="urls.length > 1">
                <template x-for="(u, k) in urls" :key="k">
                    <button type="button" :class="k === i && 'active'" @click="i = k" :aria-label="{{ Js::from(__('Foto')) }} + ' ' + (k + 1)"><img :src="u" alt=""></button>
                </template>
            </div>
            @foreach ($expense->receipts->filter(fn ($r) => str_ends_with($r->path, '.pdf')) as $pdf)
                <a href="{{ route('files.receipt', $pdf) }}" target="_blank" rel="noopener" class="btn btn-light btn-sm mt-2"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> {{ $pdf->original_name }}</a>
            @endforeach
        </div>

        {{-- Kanan: data & keputusan --}}
        <div class="d-grid gap-3 align-content-start">
            <x-ui.card>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="pm-stat__label">{{ __('Nominal') }}</div>
                        <div class="pm-stat__value" style="font-size: var(--pm-fs-2xl)">@rupiah($expense->amount)</div>
                    </div>
                    <x-ui.badge :status="$expense->status" />
                </div>
                <dl class="pm-dl">
                    <dt>{{ __('Keterangan') }}</dt><dd>{{ $expense->description }}</dd>
                    <dt>{{ __('Villa') }}</dt><dd>{{ $expense->villa->name }}</dd>
                    <dt>{{ __('Kategori') }}</dt><dd>{{ __($expense->category->name) }}</dd>
                    <dt>{{ __('Tanggal nota') }}</dt><dd>{{ $expense->expense_date->translatedFormat('d F Y') }}</dd>
                    <dt>{{ __('Vendor') }}</dt><dd>{{ $expense->vendor ?: '—' }}</dd>
                    <dt>{{ __('Metode bayar') }}</dt><dd>{{ $expense->payment_method?->label() ?? '—' }}</dd>
                    <dt>{{ __('Diunggah') }}</dt><dd>{{ $expense->creator?->name }} · {{ $expense->created_at->translatedFormat('d M Y H:i') }}</dd>
                    @if ($expense->notes)<dt>{{ __('Catatan') }}</dt><dd class="fw-normal">{{ $expense->notes }}</dd>@endif
                    @if ($expense->return_reason)<dt>{{ __('Alasan sebelumnya') }}</dt><dd class="text-negative">{{ $expense->return_reason }}</dd>@endif
                </dl>
            </x-ui.card>

            @if ($locked)
                <div class="alert alert-info mb-0"><i class="bi bi-lock" aria-hidden="true"></i> {{ __('Bulan nota ini sudah Final. Buka kunci laporan untuk mengubah status.') }}</div>
            @else
                <x-ui.card :title="__('Keputusan')" x-data="{ mode: '{{ $errors->has('return_reason') ? 'return' : 'idle' }}' }">
                    <div x-show="mode !== 'return'" class="d-grid gap-2">
                        <form method="POST" action="{{ route('reviews.approve', $expense) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-lg w-100" style="background: var(--pm-success); border-color: var(--pm-success)" data-loading-text="{{ __('Menyimpan…') }}">
                                <i class="bi bi-check-circle" aria-hidden="true"></i> {{ __('Valid') }}
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger-soft btn-lg" @click="mode = 'return'; $nextTick(() => $refs.reason.focus())">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> {{ __('Kembalikan ke Staff') }}
                        </button>
                    </div>
                    <form method="POST" action="{{ route('reviews.return', $expense) }}" x-show="mode === 'return'" x-cloak data-validate novalidate>
                        @csrf
                        <div class="mb-2 d-flex flex-wrap gap-1" aria-label="{{ __('Alasan cepat') }}">
                            @foreach ([__('Foto nota buram / tidak terbaca'), __('Nominal tidak sesuai nota'), __('Kategori salah'), __('Nota tidak lengkap'), __('Tanggal tidak sesuai')] as $quick)
                                <button type="button" class="btn btn-sm btn-light" @click="$refs.reason.value = @js($quick)">{{ $quick }}</button>
                            @endforeach
                        </div>
                        <x-form.textarea name="return_reason" :label="__('Alasan dikembalikan')" rows="3" required minlength="5" maxlength="500" x-ref="reason"
                                         :placeholder="__('Jelaskan apa yang perlu diperbaiki Staff')" />
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light" @click="mode = 'idle'">{{ __('Batal') }}</button>
                            <button type="submit" class="btn btn-danger flex-grow-1" data-loading-text="{{ __('Mengirim…') }}"><i class="bi bi-send" aria-hidden="true"></i> {{ __('Kirim ke Staff') }}</button>
                        </div>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
