<?php

namespace App\Http\Controllers;

use App\Models\MonthlyReport;
use App\Models\Villa;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public const PRESETS = [
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'last_3' => '3 bulan terakhir',
        'last_6' => '6 bulan terakhir',
        'last_12' => '12 bulan terakhir',
        'this_year' => 'Tahun ini',
        'last_year' => 'Tahun lalu',
        'custom' => 'Rentang custom',
    ];

    public function __construct(private FinanceCalculator $calc, private ReportService $reports) {}

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', MonthlyReport::class);
        $user = $request->user();

        $p = $request->validate([
            'preset' => ['nullable', 'in:'.implode(',', array_keys(self::PRESETS))],
            'from' => ['nullable', 'date', 'required_if:preset,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:preset,custom'],
            'granularity' => ['nullable', 'in:month,quarter,year'],
            'villa' => ['nullable', 'integer'],
            'trend' => ['nullable', 'in:6,12,24'],
        ]);
        $preset = $p['preset'] ?? 'last_6';
        $granularity = $p['granularity'] ?? 'month';
        $villaId = $p['villa'] ?? null;
        abort_if($villaId && ! $user->canAccessVilla($villaId), 403);
        $trend = (int) ($p['trend'] ?? 12);

        [$from, $to] = $this->range($preset, $p['from'] ?? null, $p['to'] ?? null);
        $filter = $this->scoped($user, FinanceFilter::for($user, $from, $to, $villaId));

        $current = $this->calc->summary($filter);
        $length = (int) $from->diffInMonths($to->copy()->addDay()) ?: 1;
        $prevFilter = $filter->withPeriod($from->copy()->subMonthsNoOverflow($length), $from->copy()->subDay());
        $yoyFilter = $filter->withPeriod($from->copy()->subYear(), $to->copy()->subYear());

        $trendFilter = $filter->withPeriod(today()->startOfMonth()->subMonths($trend - 1), today()->endOfMonth());

        return view('analytics.index', [
            'presets' => self::PRESETS,
            'params' => ['preset' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'granularity' => $granularity, 'villa' => $villaId, 'trend' => $trend],
            'periodLabel' => $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y'),
            'current' => $current,
            'previous' => $this->calc->summary($prevFilter),
            'yoy' => $this->calc->summary($yoyFilter),
            'prevLabel' => $prevFilter->from->translatedFormat('M Y').' – '.$prevFilter->to->translatedFormat('M Y'),
            'yoyLabel' => $yoyFilter->from->translatedFormat('M Y').' – '.$yoyFilter->to->translatedFormat('M Y'),
            'rows' => $this->calc->series($filter, $granularity),
            'trendSeries' => $this->calc->series($this->scoped($user, $trendFilter), 'month'),
            'byPlatform' => $this->calc->byPlatform($filter),
            'byCategory' => $this->calc->byCategory($filter),
            'byVilla' => $this->calc->byVilla($filter->withVillas($filter->villaIds)),
            'villas' => Villa::accessibleBy($user)->orderBy('name')->pluck('name', 'id'),
            'restricted' => $filter->monthScope !== null,
        ]);
    }

    private function scoped($user, FinanceFilter $f): FinanceFilter
    {
        if ($user->isManager()) {
            return $f;
        }
        $ids = $f->villaIds ?? [];

        return $f->withMonthScope($this->reports->finalMonths($ids, $f->from, $f->to));
    }

    private function range(string $preset, ?string $from, ?string $to): array
    {
        $t = today();

        return match ($preset) {
            'this_month' => [$t->copy()->startOfMonth(), $t->copy()->endOfMonth()],
            'last_month' => [$t->copy()->subMonthNoOverflow()->startOfMonth(), $t->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_3' => [$t->copy()->subMonths(2)->startOfMonth(), $t->copy()->endOfMonth()],
            'last_12' => [$t->copy()->subMonths(11)->startOfMonth(), $t->copy()->endOfMonth()],
            'this_year' => [$t->copy()->startOfYear(), $t->copy()->endOfYear()],
            'last_year' => [$t->copy()->subYear()->startOfYear(), $t->copy()->subYear()->endOfYear()],
            'custom' => [Carbon::parse($from), Carbon::parse($to)],
            default => [$t->copy()->subMonths(5)->startOfMonth(), $t->copy()->endOfMonth()],
        };
    }
}
