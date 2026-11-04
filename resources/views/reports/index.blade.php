<x-layouts.app :title="__('Laporan')">
    <x-ui.page-header :title="__('Laporan')" :subtitle="auth()->user()->isOwner() ? __('Laporan bulanan yang sudah Final dapat dilihat & diunduh.') : __('Periksa laporan tiap villa lalu tandai Final untuk mengunci bulan.')">
        <x-slot:actions>
            <a href="{{ route('reports.annual', ['year' => $year]) }}" class="btn btn-light"><i class="bi bi-calendar-range" aria-hidden="true"></i> {{ __('Laporan Tahunan') }}</a>
            <a href="{{ route('reports.monthly') }}" class="btn btn-primary"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ __('Lihat Laporan') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush>
        <div class="pm-toolbar">
            <a href="{{ route('reports.index', ['year' => $year - 1]) }}" class="btn btn-light" aria-label="{{ __('Tahun sebelumnya') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <strong class="text-ink fs-5 px-2">{{ $year }}</strong>
            <a href="{{ route('reports.index', ['year' => $year + 1]) }}" class="btn btn-light" aria-label="{{ __('Tahun berikutnya') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <div class="pm-legend ms-auto">
                <span><x-ui.badge variant="info">{{ __('Final') }}</x-ui.badge></span>
                <span><x-ui.badge variant="neutral">{{ __('Draft') }}</x-ui.badge></span>
            </div>
        </div>
        @if ($villas->isEmpty())
            <x-ui.empty-state art="villa" :title="__('Belum ada villa')" />
        @else
            <div class="table-responsive">
                <table class="table pm-table mb-0" style="min-width: 900px">
                    <thead><tr><th>{{ __('Villa') }}</th>
                        @for ($m = 1; $m <= 12; $m++)<th class="text-center">{{ \Illuminate\Support\Carbon::create($year, $m, 1)->translatedFormat('M') }}</th>@endfor
                    </tr></thead>
                    <tbody>
                    @foreach ($villas as $v)
                        <tr>
                            <td class="cell-title text-nowrap"><a href="{{ route('reports.annual', ['villa' => $v->id, 'year' => $year]) }}" class="text-decoration-none">{{ $v->name }}</a></td>
                            @for ($m = 1; $m <= 12; $m++)
                                @php
                                    $r = $reports[$v->id][$m] ?? null;
                                    $isFinal = $r?->status === \App\Enums\ReportStatus::Final;
                                    $future = \Illuminate\Support\Carbon::create($year, $m, 1)->isFuture();
                                @endphp
                                <td class="text-center px-1">
                                    @if ($future)
                                        <span class="text-muted fs-xs">—</span>
                                    @elseif ($isFinal || auth()->user()->isManager())
                                        <a href="{{ route('reports.monthly', ['villa' => $v->id, 'year' => $year, 'month' => $m]) }}" class="text-decoration-none" aria-label="{{ __('Laporan :villa bulan :month', ['villa' => $v->name, 'month' => $m]) }}">
                                            <x-ui.badge :variant="$isFinal ? 'info' : 'neutral'">{{ $isFinal ? __('Final') : __('Draft') }}</x-ui.badge>
                                        </a>
                                    @else
                                        <span class="fs-xs text-muted">{{ __('Belum') }}</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</x-layouts.app>
