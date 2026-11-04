<?php

namespace App\Http\Controllers\Manager;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use App\Services\IcalSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SyncController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('sync', Booking::class);

        $villas = Villa::query()->active()->accessibleBy($request->user())
            ->with(['channels.platform'])
            ->withCount(['bookings as needs_completion_count' => fn ($q) => $q->where('status', BookingStatus::NeedsCompletion->value)])
            ->orderBy('name')->get();

        $channels = $villas->flatMap->channels;
        $summary = [
            'total' => $channels->count(),
            'ok' => $channels->filter(fn ($c) => $c->last_success_at && ! $c->last_error)->count(),
            'error' => $channels->filter(fn ($c) => $c->last_error)->count(),
            'never' => $channels->filter(fn ($c) => ! $c->last_synced_at)->count(),
            'last' => $channels->max('last_synced_at'),
        ];

        return view('sync.index', compact('villas', 'summary'));
    }

    /** Tombol "Sinkron sekarang" — semua villa atau satu villa. */
    public function run(Request $request, IcalSyncService $sync): RedirectResponse
    {
        $this->authorize('sync', Booking::class);
        @set_time_limit(120);

        $villaId = $request->integer('villa') ?: null;
        abort_if($villaId && ! $request->user()->canAccessVilla($villaId), 403);
        $results = $villaId ? $sync->syncVilla($villaId) : $sync->syncAll($request->user()->accessibleVillaIds());

        if (! $results) {
            return back()->with('warning', __('Belum ada link iCal untuk disinkronkan. Tambahkan di halaman edit villa.'));
        }

        $errors = collect($results)->whereNotNull('error');
        $created = collect($results)->sum('created');
        $cancelled = collect($results)->sum('cancelled');
        $msg = __('Sinkron selesai: :created reservasi baru, :cancelled dibatalkan.', ['created' => $created, 'cancelled' => $cancelled]);

        return $errors->isEmpty()
            ? back()->with('success', $msg)
            : back()->with('error', $msg.' '.__(':count link gagal — lihat detail di tabel.', ['count' => $errors->count()]));
    }
}
