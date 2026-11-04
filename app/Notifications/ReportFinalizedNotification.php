<?php

namespace App\Notifications;

use App\Models\MonthlyReport;

/** Ke Owner: laporan bulanan villanya sudah Final. */
class ReportFinalizedNotification extends AppNotification
{
    public function __construct(public MonthlyReport $report) {}

    protected function title(): string
    {
        return __('Laporan :period sudah Final', ['period' => $this->period()]);
    }

    protected function body(): string
    {
        return sprintf(__('Laporan keuangan %s periode %s sudah diperiksa Manager dan siap dilihat atau diunduh (PDF & Excel).'),
            $this->report->loadMissing('villa:id,name')->villa?->name, $this->period());
    }

    protected function url(): string
    {
        return route('reports.monthly', ['villa' => $this->report->villa_id, 'year' => $this->report->year, 'month' => $this->report->month]);
    }

    protected function icon(): string
    {
        return 'bi-file-earmark-check';
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function actionText(): string
    {
        return __('Lihat laporan');
    }

    private function period(): string
    {
        return \Illuminate\Support\Carbon::create($this->report->year, $this->report->month, 1)->translatedFormat('F Y');
    }
}
