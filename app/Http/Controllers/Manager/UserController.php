<?php

namespace App\Http\Controllers\Manager;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use App\Services\VillaAssignmentLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $actor = $request->user();
        $users = User::query()
            ->with(['roles:id,name', 'assignedVillas:id,name', 'ownedVillas:id,name,owner_id', 'regions:id,name'])
            ->tap(fn ($q) => $this->scopeManageable($q, $actor))
            ->when($filters['role'] ?? null, fn ($q, $r) => $q->role($r))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('username', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(15)->withQueryString();

        // Hitungan per tab role
        $counts = ['all' => User::query()->tap(fn ($q) => $this->scopeManageable($q, $actor))->count()];
        foreach (UserRole::cases() as $role) {
            $counts[$role->value] = User::role($role->value)->tap(fn ($q) => $this->scopeManageable($q, $actor))->count();
        }

        return view('users.index', compact('users', 'filters', 'counts'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        $user = new User(['is_active' => true]);
        $presetRole = UserRole::tryFrom((string) $request->query('role')) ?? UserRole::Staff;

        return view('users.create', $this->formData($user, $presetRole, []));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'],
            ]);
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
            app(VillaAssignmentLimits::class)->forUser($user, UserRole::from($data['role']), $data['villa_ids'] ?? [], $data['region_ids'] ?? [], request()->user());
            $user->syncRoles([$data['role']]);
            $this->syncVillaAccess($user, UserRole::from($data['role']), $data['villa_ids'] ?? [], request()->user());
            $this->syncRegions($user, UserRole::from($data['role']), $data['region_ids'] ?? [], request()->user());

            return $user;
        });

        return redirect()->route('users.index')->with('success', __('Akun :name berhasil dibuat. Bagikan email & kata sandi ke pengguna.', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load(['assignedVillas:id', 'ownedVillas:id,owner_id', 'regions:id']);
        $role = $user->roleEnum() ?? UserRole::Staff;
        $selected = $role === UserRole::Owner
            ? $user->ownedVillas->pluck('id')->all()
            : $user->assignedVillas->pluck('id')->all();

        return view('users.edit', $this->formData($user, $role, $selected));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            app(VillaAssignmentLimits::class)->forUser($user, UserRole::from($data['role']), $data['villa_ids'] ?? [], $data['region_ids'] ?? [], request()->user());
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'],
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->syncRoles([$data['role']]);
            $this->syncVillaAccess($user, UserRole::from($data['role']), $data['villa_ids'] ?? [], request()->user());
            $this->syncRegions($user, UserRole::from($data['role']), $data['region_ids'] ?? [], request()->user());
        });

        return redirect()->route('users.index')->with('success', "Akun {$user->name} diperbarui.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? __('Akun :name diaktifkan kembali.', ['name' => $user->name])
            : __('Akun :name dinonaktifkan dan tidak bisa login.', ['name' => $user->name]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->assignedVillas()->detach();
            Villa::where('owner_id', $user->id)->get()->each(fn (Villa $v) => $v->update(['owner_id' => null]));
            $user->update(['is_active' => false]);
            $user->delete(); // soft delete: riwayat transaksi yang dibuatnya tetap utuh
        });

        return redirect()->route('users.index')->with('success', __('Akun :name dihapus.', ['name' => $user->name]));
    }

    /**
     * Staff   → villa yang ditugaskan (pivot villa_user, bisa lebih dari satu).
     * Owner   → villa yang dimiliki (villas.owner_id).
     * Manager → lewat wilayah (syncRegions). Master → semua villa.
     *
     * Manager wilayah hanya mengubah penugasan di villa wilayahnya; penugasan Staff
     * di wilayah lain (diatur Master / manager lain) tetap dipertahankan.
     */
    private function syncVillaAccess(User $user, UserRole $role, array $villaIds, User $actor): void
    {
        $villaIds = array_map('intval', $villaIds);
        $scope = $actor->accessibleVillaIds(); // null = Master

        if ($role === UserRole::Staff) {
            if ($scope !== null) {
                $outside = $user->assignedVillas()->pluck('villas.id')->map(fn ($id) => (int) $id)->diff($scope)->all();
                $villaIds = array_values(array_unique(array_merge($outside, array_intersect($villaIds, $scope))));
            }
            $user->assignedVillas()->sync($villaIds);
        } else {
            $user->assignedVillas()->detach();
        }

        if (! $actor->isMaster()) {
            $user->forgetVillaAccessCache();

            return; // kepemilikan villa (Owner) hanya diatur Master
        }

        // Update per model (bukan mass update) agar perubahan owner tercatat di audit log.
        $keep = $role === UserRole::Owner ? $villaIds : [];
        Villa::where('owner_id', $user->id)->whereNotIn('id', $keep ?: [0])->get()
            ->each(fn (Villa $v) => $v->update(['owner_id' => null]));
        if ($keep) {
            Villa::whereIn('id', $keep)->where(fn ($q) => $q->whereNull('owner_id')->orWhere('owner_id', '!=', $user->id))->get()
                ->each(fn (Villa $v) => $v->update(['owner_id' => $user->id]));
        }

        $user->forgetVillaAccessCache();
    }

    /** Manager → wilayah yang dipegang (khusus diatur Master). */
    private function syncRegions(User $user, UserRole $role, array $regionIds, User $actor): void
    {
        if (! $actor->isMaster()) {
            return;
        }
        $user->regions()->sync($role === UserRole::Manager ? array_map('intval', $regionIds) : []);
        $user->forgetVillaAccessCache();
    }

    /** Master melihat semua akun; Manager wilayah hanya Staff di villa wilayahnya + Staff yang belum ditugaskan. */
    private function scopeManageable($query, User $actor): void
    {
        if ($actor->isMaster()) {
            return;
        }
        $query->role(UserRole::Staff->value)->where(fn ($q) => $q
            ->whereHas('assignedVillas', fn ($v) => $v->whereIn('villas.id', $actor->accessibleVillaIds() ?: [0]))
            ->orWhereDoesntHave('assignedVillas'));
    }

    private function formData(User $user, UserRole $role, array $selectedVillaIds): array
    {
        $actor = auth()->user();

        return [
            'user' => $user,
            'role' => $role,
            'selectedVillaIds' => array_map('intval', old('villa_ids', $selectedVillaIds)),
            'villas' => Villa::query()->accessibleBy($actor)->with(['owner:id,name', 'region:id,name'])
                ->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'location', 'owner_id', 'region_id', 'is_active']),
            'roles' => array_values(array_filter(UserRole::cases(), fn (UserRole $r) => $actor->can('assignRole', [User::class, $r]))),
            'regions' => $actor->isMaster() ? Region::withCount('villas')->orderBy('name')->get() : collect(),
            'selectedRegionIds' => array_map('intval', old('region_ids', $user->exists ? $user->regions()->pluck('regions.id')->all() : [])),
        ];
    }
}
