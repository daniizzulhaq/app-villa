<x-layouts.app :title="__('Laporan')">
    <x-ui.page-header icon="bi-file-earmark-bar-graph" :title="__('Laporan')" :subtitle="auth()->user()->isOwner() ? __('Laporan bulanan yang sudah Final dapat dilihat & diunduh.') : __('Periksa laporan tiap villa lalu tandai Final untuk mengunci bulan.')">
        <x-slot:actions>
            <a href="{{ route('reports.annual', ['year' => $year]) }}" class="btn btn-light"><i class="bi bi-calendar-range" aria-hidden="true"></i>{{ __('Laporan Tahunan') }}</a>
            <a href="{{ route('reports.monthly') }}" class="btn btn-primary"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>{{ __('Lihat Laporan') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="pm-report-toolbar">
        <div class="pm-report-toolbar__year">
            <a href="{{ route('reports.index', ['year' => $year - 1]) }}" class="btn btn-light btn-icon" aria-label="{{ __('Tahun sebelumnya') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <strong class="num">{{ $year }}</strong>
            <a href="{{ route('reports.index', ['year' => $year + 1]) }}" class="btn btn-light btn-icon" aria-label="{{ __('Tahun berikutnya') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </div>
        <div class="pm-legend"><span><x-ui.badge variant="success">{{ __('Final') }}</x-ui.badge></span><span><x-ui.badge variant="neutral">{{ __('Draft') }}</x-ui.badge></span><span><i class="bi bi-lock" aria-hidden="true"></i>{{ __('Belum tersedia') }}</span></div>
    </div>
    <p class="pm-report-help"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ auth()->user()->isOwner() ? __('Laporan Final siap dilihat dan diunduh.') : __('Pilih bulan untuk membuka laporan villa.') }}</p>

    @if ($villas->isEmpty())
        <x-ui.card><x-ui.empty-state art="villa" :title="__('Belum ada villa')" /></x-ui.card>
    @else
        <div class="pm-report-grid">
            @foreach ($villas as $v)
                @php
                    $villaReports = $reports->get($v->id, collect());
                    $finalCount = $villaReports->filter(fn ($report) => $report->status === \App\Enums\ReportStatus::Final)->count();
                @endphp
                <section class="pm-card pm-report-property" aria-labelledby="report-villa-{{ $v->id }}">
                    <header class="pm-report-property__header">
                        <span class="pm-module-icon" aria-hidden="true"><i class="bi bi-house-door"></i></span>
                        <div><h2 id="report-villa-{{ $v->id }}"><a href="{{ route('reports.annual', ['villa' => $v->id, 'year' => $year]) }}">{{ $v->name }}</a></h2><p>{{ __(':count laporan final', ['count' => $finalCount]) }} · {{ $year }}</p></div>
                        <a href="{{ route('reports.annual', ['villa' => $v->id, 'year' => $year]) }}" class="btn btn-ghost btn-icon" aria-label="{{ __('Laporan Tahunan').' · '.$v->name }}"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </header>
                    <div class="pm-report-months">
                        @for ($m = 1; $m <= 12; $m++)
                            @php
                                $monthDate = \Illuminate\Support\Carbon::create($year, $m, 1);
                                $report = $villaReports->get($m);
                                $isFinal = $report?->status === \App\Enums\ReportStatus::Final;
                                $available = ! $monthDate->isFuture() && ($isFinal || auth()->user()->isManager());
                            @endphp
                            @if ($available)
                                <a href="{{ route('reports.monthly', ['villa' => $v->id, 'year' => $year, 'month' => $m]) }}" @class(['pm-report-month', 'pm-report-month--final' => $isFinal]) aria-label="{{ __('Laporan :villa bulan :month', ['villa' => $v->name, 'month' => $monthDate->translatedFormat('F Y')]) }}">
                                    <span>{{ $monthDate->translatedFormat('M') }}</span>
                                    <x-ui.badge :variant="$isFinal ? 'success' : 'neutral'">{{ $isFinal ? __('Final') : __('Draft') }}</x-ui.badge>
                                </a>
                            @else
                                <div class="pm-report-month pm-report-month--disabled" aria-disabled="true">
                                    <span>{{ $monthDate->translatedFormat('M') }}</span>
                                    <span class="pm-report-month__state"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ __('Belum') }}</span>
                                </div>
                            @endif
                        @endfor
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
