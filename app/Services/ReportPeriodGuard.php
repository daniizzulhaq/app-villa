<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\MonthlyReport;
use App\Models\Villa;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** All calls belong inside the transaction that writes the financial data. */
class ReportPeriodGuard
{
    public function lockVillas(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));

        return Villa::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id')->all();
    }

    public function assertScope(int $villaId, array $lockedIds): void
    {
        if (! in_array($villaId, $lockedIds, true)) {
            throw ValidationException::withMessages(['villa_id' => __('The transaction changed. Reload the page and try again.')]);
        }
    }

    public function assertOpen(int $villaId, DateTimeInterface|string $date, string $field): void
    {
        $date = Carbon::parse($date);
        $report = MonthlyReport::where('villa_id', $villaId)->where('year', $date->year)->where('month', $date->month)
            ->lockForUpdate()->first();
        if ($report?->status === ReportStatus::Final) {
            throw ValidationException::withMessages([$field => __('Laporan bulan ini sudah Final dan terkunci. Buka kunci di menu Laporan untuk mengubah data.')]);
        }
    }
}
