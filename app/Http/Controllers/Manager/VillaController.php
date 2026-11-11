<?php

namespace App\Http\Controllers\Manager;

use App\Enums\FeeBasis;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\VillaRequest;
use App\Models\BookingPlatform;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use App\Services\VillaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VillaController extends Controller
{
    public function __construct(private VillaService $villas) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Villa::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'owner' => ['nullable', 'integer'],
        ]);

        $villas = Villa::query()
            ->accessibleBy($request->user())
            ->with(['owner:id,name', 'region:id,name', 'channels.platform', 'staff:id,name'])
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('location', 'like', "%{$s}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['owner'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(12)->withQueryString();

        $owners = User::role(UserRole::Owner->value)->orderBy('name')->pluck('name', 'id');

        return view('villas.index', compact('villas', 'filters', 'owners'));
    }

    public function create(): View
    {
        $this->authorize('create', Villa::class);

        return view('villas.create', $this->formData(new Villa([
            'fee_percentage' => 15,
            'fee_basis' => FeeBasis::NetOfPlatform,
            'timezone' => config('app.timezone'),
            'is_active' => true,
            'started_at' => today(),
        ])));
    }

    public function store(VillaRequest $request): RedirectResponse
    {
        $villa = $this->villas->create($request->validated(), $request->user());

        return redirect()->route('villas.show', $villa)->with('success', __('Villa ":name" berhasil ditambahkan.', ['name' => $villa->name]));
    }

    public function show(Villa $villa): View
    {
        $this->authorize('view', $villa);

        $villa->load(['owner', 'region.managers:id,name', 'staff' => fn ($q) => $q->orderBy('name'), 'channels.platform', 'feeHistories.changer:id,name']);

        return view('villas.show', compact('villa'));
    }

    public function edit(Villa $villa): View
    {
        $this->authorize('update', $villa);

        $villa->load(['staff:id', 'channels.platform']);

        return view('villas.edit', $this->formData($villa));
    }

    public function update(VillaRequest $request, Villa $villa): RedirectResponse
    {
        $oldPct = (string) $villa->fee_percentage;
        $oldBasis = $villa->fee_basis;

        $this->villas->update($villa, $request->validated(), $request->user());

        $message = __('Perubahan villa ":name" tersimpan.', ['name' => $villa->name]);
        if (bccomp($oldPct, (string) $villa->fee_percentage, 2) !== 0 || $oldBasis !== $villa->fee_basis) {
            $message .= ' '.__('Fee baru tercatat di riwayat dan hanya berlaku untuk pemasukan mulai tanggal berlaku.');
        }

        return redirect()->route('villas.show', $villa)->with('success', $message);
    }

    /**
     * Villa yang sudah punya transaksi tidak dihapus (agar laporan lama tetap utuh) — cukup dinonaktifkan.
     */
    public function destroy(Villa $villa): RedirectResponse
    {
        $this->authorize('delete', $villa);

        if ($villa->incomes()->exists() || $villa->expenses()->exists() || $villa->bookings()->exists()) {
            return back()->with('error', __('Villa ini sudah memiliki reservasi atau transaksi. Nonaktifkan saja agar laporan lama tetap utuh.'));
        }

        $villa->staff()->detach();
        $villa->delete();

        return redirect()->route('villas.index')->with('success', "Villa \"{$villa->name}\" dihapus.");
    }

    public function toggleActive(Villa $villa): RedirectResponse
    {
        $this->authorize('delete', $villa);

        $villa->update(['is_active' => ! $villa->is_active]);

        return back()->with('success', $villa->is_active ? __('Villa diaktifkan kembali.') : __('Villa dinonaktifkan. Data lama tetap tersimpan.'));
    }

    public function disableIcal(Villa $villa): RedirectResponse
    {
        $this->authorize('update', $villa);

        DB::transaction(function () use ($villa) {
            $current = Villa::lockForUpdate()->findOrFail($villa->id);
            abort_unless(config('propertyme.channel_manager_provider') === 'hostex' && $current->hostex_property_id,
                422, __('Map this villa to Hostex before disabling iCal here.'));

            // Keep the feeds and their history; only stop future imports for this villa.
            $current->channels()->where('is_active', true)
                ->whereNotNull('ical_url')->where('ical_url', '!=', '')
                ->update(['is_active' => false]);
        });

        return redirect()->route('villas.show', $villa)->with('success',
            __('iCal disabled for this villa. Existing bookings and income records were kept.'));
    }

    private function formData(Villa $villa): array
    {
        $villa->loadMissing('owner:id,name', 'region:id,name');

        return [
            'villa' => $villa,
            'isMaster' => auth()->user()->isMaster(),
            'regions' => Region::orderBy('name')->pluck('name', 'id'),
            'owners' => User::role(UserRole::Owner->value)->active()->orderBy('name')->pluck('name', 'id'),
            'staffUsers' => User::role(UserRole::Staff->value)->active()->orderBy('name')->get(['id', 'name', 'phone']),
            'icalPlatforms' => BookingPlatform::icalPlatforms(),
            'feeBases' => FeeBasis::cases(),
            'timezones' => [
                'Asia/Makassar' => __('WITA — Bali, Lombok (Asia/Makassar)'),
                'Asia/Jakarta' => __('WIB — Jawa, Sumatra (Asia/Jakarta)'),
                'Asia/Jayapura' => __('WIT — Papua (Asia/Jayapura)'),
            ],
        ];
    }
}
