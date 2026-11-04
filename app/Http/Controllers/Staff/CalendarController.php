<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Villa;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Layar utama Staff: panel Hari ini & Besok di atas (yang paling sering dicari),
 * kalender di bawah. Tidak ada nominal pendapatan sama sekali.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $villas = Villa::accessibleBy($user)->active()->orderBy('name')->get(['id', 'name']);
        $villaId = $request->integer('villa') ?: null;
        if ($villaId && ! $villas->contains('id', $villaId)) {
            abort(403);
        }

        $today = today();
        $tomorrow = today()->addDay();
        $base = fn () => Booking::query()
            ->with(['villa:id,name', 'platform:id,name,color'])
            ->whereIn('villa_id', $villaId ? [$villaId] : $villas->pluck('id'))
            ->where('status', '!=', BookingStatus::Cancelled->value);

        $panels = [
            'today' => [
                'label' => __('Hari ini'),
                'date' => $today,
                'in' => $base()->whereDate('check_in', $today)->orderBy('arrival_time')->get(),
                'out' => $base()->whereDate('check_out', $today)->get(),
            ],
            'tomorrow' => [
                'label' => __('Besok'),
                'date' => $tomorrow,
                'in' => $base()->whereDate('check_in', $tomorrow)->orderBy('arrival_time')->get(),
                'out' => $base()->whereDate('check_out', $tomorrow)->get(),
            ],
        ];

        $occupiedNow = $base()->where('check_in', '<=', $today)->where('check_out', '>', $today)->pluck('villa_id')->unique();

        return view('staff.calendar', [
            'villas' => $villas,
            'villaId' => $villaId,
            'panels' => $panels,
            'occupiedNow' => $occupiedNow,
            'platforms' => BookingPlatform::orderBy('sort_order')->get(),
            'initialDate' => $request->date('date')?->toDateString(),
        ]);
    }
}
