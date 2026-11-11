<?php

namespace App\Http\Controllers\Manager;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Villa;
use App\Services\HostexSyncService;
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

        $hostexVillas = config('propertyme.channel_manager_provider') === 'hostex' ? $villas->whereNotNull('hostex_property_id') : collect();
        $channels = $villas->whereNotIn('id', $hostexVillas->pluck('id')->all())->flatMap->channels;
        $summary = [
            'total' => $channels->count() + $hostexVillas->count(),
            'ok' => $channels->filter(fn ($c) => $c->last_success_at && ! $c->last_error)->count() + $hostexVillas->filter(fn ($v) => $v->hostex_last_success_at && ! $v->hostex_last_error)->count(),
            'error' => $channels->filter(fn ($c) => $c->last_error)->count() + $hostexVillas->whereNotNull('hostex_last_error')->count(),
            'never' => $channels->filter(fn ($c) => ! $c->last_synced_at)->count(),
            'last' => $channels->pluck('last_synced_at')->merge($hostexVillas->pluck('hostex_last_synced_at'))->filter()->max(),
        ];

        return view('sync.index', compact('villas', 'summary'));
    }

    /** Tombol "Sinkron sekarang" — semua villa atau satu villa. */
    public function run(Request $request, IcalSyncService $sync, HostexSyncService $hostex): RedirectResponse
    {
        $this->authorize('sync', Booking::class);
        @set_time_limit(120);

        $villaId = $request->integer('villa') ?: null;
        abort_if($villaId && ! $request->user()->canAccessVilla($villaId), 403);
        $hostexVillas = config('propertyme.channel_manager_provider') === 'hostex'
            ? Villa::active()->accessibleBy($request->user())->whereNotNull('hostex_property_id')->when($villaId, fn ($q) => $q->whereKey($villaId))->get()
            : collect();
        $results = $villaId && $hostexVillas->isNotEmpty() ? [] : ($villaId ? $sync->syncVilla($villaId) : $sync->syncAll($request->user()->accessibleVillaIds()));
        foreach ($hostexVillas as $villa) {
            $results[] = $hostex->syncVilla($villa);
        }

        if (! $results) {
            return back()->with('warning', __('Belum ada link iCal untuk disinkronkan. Tambahkan di halaman edit villa.'));
        }

        $errors = collect($results)->whereNotNull('error');
        $created = collect($results)->sum('created');
        $cancelled = collect($results)->sum('cancelled');
        $msg = __('Sinkron selesai: :created reservasi baru, :cancelled dibatalkan.', ['created' => $created, 'cancelled' => $cancelled]);
        if (config('services.hostex.auto_income_enabled')) {
            $msg .= ' '.__('Automatic income: :created created, :updated updated, :voided archived, :pending need review.', [
                'created' => collect($results)->sum('income_created'), 'updated' => collect($results)->sum('income_updated'),
                'voided' => collect($results)->sum('income_voided'), 'pending' => collect($results)->sum('income_pending'),
            ]);
        }

        return $errors->isEmpty()
            ? back()->with('success', $msg)
            : back()->with('error', $msg.' '.$errors->first()['error']);
    }
}
