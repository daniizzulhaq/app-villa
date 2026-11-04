<?php

namespace App\Http\Controllers\Manager;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Wilayah (khusus Master): kelompokkan villa per area, lalu tugaskan Manager ke wilayah.
 * Manager otomatis mendapat akses semua villa di wilayahnya — termasuk villa yang ditambahkan kemudian.
 */
class RegionController extends Controller
{
    public function index(): View
    {
        $regions = Region::query()
            ->with(['villas' => fn ($q) => $q->orderBy('name')->select('id', 'name', 'region_id', 'is_active'), 'managers:id,name,is_active'])
            ->orderBy('name')->get();

        $unassigned = Villa::whereNull('region_id')->orderBy('name')->get(['id', 'name', 'is_active']);

        return view('regions.index', compact('regions', 'unassigned'));
    }

    public function create(): View
    {
        return view('regions.create', $this->formData(new Region));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $region = DB::transaction(function () use ($data) {
            $region = Region::create(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $this->syncMembers($region, $data);

            return $region;
        });

        return redirect()->route('regions.index')->with('success', __('Wilayah ":name" berhasil dibuat.', ['name' => $region->name]));
    }

    public function edit(Region $region): View
    {
        return view('regions.edit', $this->formData($region));
    }

    public function update(Request $request, Region $region): RedirectResponse
    {
        $data = $this->validated($request, $region);

        DB::transaction(function () use ($region, $data) {
            $region->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $this->syncMembers($region, $data);
        });

        return redirect()->route('regions.index')->with('success', __('Wilayah ":name" diperbarui.', ['name' => $region->name]));
    }

    /** Villa di wilayah ini menjadi "tanpa wilayah" (hanya Master yang bisa mengaksesnya) — data tidak terhapus. */
    public function destroy(Region $region): RedirectResponse
    {
        DB::transaction(function () use ($region) {
            $region->villas()->get()->each(fn (Villa $v) => $v->update(['region_id' => null]));
            $region->managers()->detach();
            $region->delete();
        });

        return redirect()->route('regions.index')->with('success', __('Wilayah ":name" dihapus. Villanya kini tanpa wilayah.', ['name' => $region->name]));
    }

    private function validated(Request $request, ?Region $region = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('regions', 'name')->ignore($region?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'villa_ids' => ['array'],
            'villa_ids.*' => ['integer', 'distinct', 'exists:villas,id'],
            'manager_ids' => ['array'],
            'manager_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ], [], [
            'name' => __('nama wilayah'),
            'villa_ids.*' => __('villa'),
            'manager_ids.*' => __('manager'),
        ]);
    }

    /** Satu villa hanya punya satu wilayah: mencentang villa di sini memindahkannya dari wilayah lama. */
    private function syncMembers(Region $region, array $data): void
    {
        $villaIds = array_map('intval', $data['villa_ids'] ?? []);

        // Per model agar perpindahan wilayah tercatat di audit log.
        $region->villas()->whereNotIn('id', $villaIds ?: [0])->get()->each(fn (Villa $v) => $v->update(['region_id' => null]));
        Villa::whereIn('id', $villaIds)->where(fn ($q) => $q->whereNull('region_id')->orWhere('region_id', '!=', $region->id))
            ->get()->each(fn (Villa $v) => $v->update(['region_id' => $region->id]));

        // Hanya akun ber-role Manager yang bisa ditugaskan ke wilayah.
        $managerIds = User::role(UserRole::Manager->value)->whereIn('id', $data['manager_ids'] ?? [])->pluck('id')->all();
        $region->managers()->sync($managerIds);
    }

    private function formData(Region $region): array
    {
        $region->loadMissing('villas:id,region_id', 'managers:id');

        return [
            'region' => $region,
            'villas' => Villa::with('region:id,name')->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'location', 'region_id', 'is_active']),
            'managers' => User::role(UserRole::Manager->value)->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'email', 'is_active']),
            'selectedVillaIds' => array_map('intval', old('villa_ids', $region->villas->pluck('id')->all())),
            'selectedManagerIds' => array_map('intval', old('manager_ids', $region->managers->pluck('id')->all())),
        ];
    }
}
