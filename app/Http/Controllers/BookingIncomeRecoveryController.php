<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\HostexIncomeRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingIncomeRecoveryController extends Controller
{
    public function show(Booking $booking, HostexIncomeRecoveryService $recovery): View
    {
        $this->authorize('update', $booking);
        abort_unless($booking->source === 'hostex', 404);
        $booking->load(['villa', 'platform']);

        return view('bookings.income-recovery', ['booking' => $booking, ...$recovery->review($booking)]);
    }

    public function store(Request $request, Booking $booking, HostexIncomeRecoveryService $recovery): RedirectResponse
    {
        $this->authorize('update', $booking);
        $data = $request->validate(['income_id' => ['required', 'integer'], 'review_token' => ['required', 'string', 'size:64']]);
        $recovery->recover($booking, $data['income_id'], $data['review_token'], $request->user());

        return redirect()->route('bookings.show', $booking)->with('success', __('Income restored and synchronized with Hostex.'));
    }
}
