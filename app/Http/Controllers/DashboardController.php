<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\ExpenseStatus;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Expense;
use App\Models\User;
use App\Models\Villa;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard Manager & Owner. Semua angka dari FinanceCalculator (tabel transaksi).
 * Filter bulan/tahun/villa/sumber mengubah kartu & grafik sekaligus.
 */
class DashboardController extends Controller
{
    public function __construct(private FinanceCalculator $calc) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->isStaff()) {
            return redirect()->route('staff.calendar');
        }

        $f = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:0,12'],
            'villa' => ['nullable', 'integer'],
            'platform' => ['nullable', 'integer'],
        ]);
        $year = $f['year'] ?? today()->year;
        $month = array_key_exists('month', $f) && $f['month'] !== null ? (int) $f['month'] : today()->month;
        $villaId = $f['villa'] ?? null;
        abort_if($villaId && ! $user->canAccessVilla($villaId), 403);

        $from = $month ? Carbon::create($year, $month, 1) : Carbon::create($year, 1, 1);
        $to = $month ? $from->copy()->endOfMonth() : $from->copy()->endOfYear();
        $filter = FinanceFilter::for($user, $from, $to, $villaId, $f['platform'] ?? null);

        $summary = $this->calc->summary($filter);

        // Grafik A: 6 bulan berakhir di periode terpilih (atau 12 bulan tahun terpilih)
        $seriesFrom = $month ? $from->copy()->subMonths(5)->startOfMonth() : $from->copy();
        $series = $this->calc->series($filter->withPeriod($seriesFrom, $to), 'month');

        $prevFilter = $filter->withPeriod($month ? $from->copy()->subMonth() : $from->copy()->subYear(), $month ? $from->copy()->subMonth()->endOfMonth() : $from->copy()->subYear()->endOfYear());
        $previous = $this->calc->summary($prevFilter);

        $villaQuery = Villa::accessibleBy($user);

        return view('dashboard.index', [
            'user' => $user,
            'filters' => ['year' => $year, 'month' => $month, 'villa' => $villaId, 'platform' => $f['platform'] ?? null],
            'periodLabel' => $month ? $from->translatedFormat('F Y') : __('Tahun :year', ['year' => $year]),
            'summary' => $summary,
            'previous' => $previous,
            'series' => $series,
            'byPlatform' => $this->calc->byPlatform($filter),
            'byCategory' => $this->calc->byCategory($filter),
            'activeVillas' => (clone $villaQuery)->active()->when($villaId, fn ($q) => $q->whereKey($villaId))->count(),
            'villas' => (clone $villaQuery)->orderBy('name')->pluck('name', 'id'),
            'platforms' => BookingPlatform::orderBy('sort_order')->pluck('name', 'id'),
            'widgets' => $this->widgets($user, $villaId),
        ]);
    }

    private function widgets(User $user, ?int $villaId): array
    {
        $villaIds = Villa::accessibleBy($user)->when($villaId, fn ($q) => $q->whereKey($villaId))->pluck('id');
        $today = today()->toDateString();
        $bookingBase = fn () => Booking::with(['villa:id,name', 'platform:id,name,color'])->whereIn('villa_id', $villaIds)
            ->where('status', '!=', BookingStatus::Cancelled->value);

        $w = [
            'checkIns' => $bookingBase()->whereDate('check_in', $today)->orderBy('arrival_time')->get(),
            'checkOuts' => $bookingBase()->whereDate('check_out', $today)->get(),
            'upcoming' => $bookingBase()->where('check_in', '>', $today)->orderBy('check_in')->limit(5)->get(),
        ];

        if ($user->isManager()) {
            $pending = Expense::where('status', ExpenseStatus::Pending->value)->whereIn('villa_id', $villaIds);
            $needs = Booking::where('status', BookingStatus::NeedsCompletion->value)->whereIn('villa_id', $villaIds);
            $w += [
                'pendingCount' => (clone $pending)->count(),
                'pendingList' => (clone $pending)->with(['villa:id,name', 'creator:id,name'])->oldest()->limit(5)->get(),
                'needsCount' => (clone $needs)->count(),
                'needsList' => (clone $needs)->with(['villa:id,name', 'platform:id,name,color'])->orderBy('check_in')->limit(5)->get(),
            ];
        }

        return $w;
    }
}
