<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/** Validate the resulting assignments inside the transaction that saves them. */
class VillaAssignmentLimits
{
    public function forUser(User $user, UserRole $role, array $villaIds, array $regionIds, User $actor): void
    {
        $this->lockUsers([$user->id]);
        $villaIds = array_unique(array_map('intval', $villaIds));
        if ($role === UserRole::Staff && ! $actor->isMaster()) {
            $outside = $user->assignedVillas()->pluck('villas.id')->diff($actor->accessibleVillaIds() ?? [])->all();
            $villaIds = array_unique(array_merge($outside, $villaIds));
        }
        $count = match ($role) {
            UserRole::Owner, UserRole::Staff => count($villaIds),
            UserRole::Manager => Villa::withTrashed()->whereIn('region_id', $regionIds)->count(),
            UserRole::Master => 0,
        };
        $previous = $user->roleEnum() === $role ? $this->count($user, $role) : 0;
        $this->check($user, $role, $count, $previous, $role === UserRole::Manager ? 'region_ids' : 'villa_ids');
    }

    public function forVilla(?Villa $villa, array $data): void
    {
        $ownerId = $data['owner_id'] ?? null;
        $staffIds = $data['staff_ids'] ?? [];
        $managerIds = empty($data['region_id']) ? [] : Region::find($data['region_id'])?->managers()->pluck('users.id')->all();
        $users = $this->lockUsers(array_merge([$ownerId], $staffIds, $managerIds ?? []));
        foreach ($users as $user) {
            $role = $user->roleEnum();
            if (! in_array($role, [UserRole::Owner, UserRole::Staff, UserRole::Manager], true)) {
                continue;
            }
            $previous = $this->count($user, $role);
            $alreadyAssigned = $villa && match ($role) {
                UserRole::Owner => (int) $villa->owner_id === $user->id,
                UserRole::Staff => $villa->staff()->whereKey($user->id)->exists(),
                UserRole::Manager => $user->regions()->whereKey($villa->region_id)->exists(),
            };
            $field = match ($role) {
                UserRole::Owner => 'owner_id',
                UserRole::Staff => 'staff_ids',
                UserRole::Manager => 'region_id',
            };
            $this->check($user, $role, $previous + ($alreadyAssigned ? 0 : 1), $previous, $field);
        }
    }

    public function forRegion(Region $region, array $villaIds, array $managerIds): void
    {
        $users = $this->lockUsers($managerIds);
        foreach ($users as $user) {
            if (! $user->isRegionalManager()) {
                continue;
            }
            $otherRegions = $user->regions()->where('regions.id', '!=', $region->id ?? 0)->pluck('regions.id');
            // Villas moved here are counted once, even if the manager also holds their old region.
            $otherCount = Villa::withTrashed()->whereIn('region_id', $otherRegions)->whereNotIn('id', $villaIds)->count();
            // Soft-deleted villas stay in their region and remain part of Manager access.
            $deletedCount = $region->exists ? Villa::onlyTrashed()->where('region_id', $region->id)->count() : 0;
            $this->check($user, UserRole::Manager, $otherCount + count(array_unique($villaIds)) + $deletedCount,
                $this->count($user, UserRole::Manager), 'manager_ids');
        }
    }

    private function lockUsers(array $ids): Collection
    {
        return User::whereIn('id', array_filter($ids))->orderBy('id')->lockForUpdate()->get();
    }

    private function count(User $user, UserRole $role): int
    {
        return match ($role) {
            UserRole::Owner => $user->ownedVillas()->count(),
            UserRole::Staff => $user->assignedVillas()->count(),
            UserRole::Manager => Villa::withTrashed()->whereIn('region_id', $user->regions()->pluck('regions.id'))->count(),
            UserRole::Master => 0,
        };
    }

    private function check(User $user, UserRole $role, int $count, int $previous, string $field): void
    {
        $limit = config('propertyme.villa_limits.'.$role->value);
        // Existing assignments can be retained or reduced when a limit is lowered.
        if ($limit !== null && $count > $limit && $count > $previous) {
            throw ValidationException::withMessages([
                $field => __(':role :name maksimal :limit villa per akun. Penugasan ini menjadi :count villa.', [
                    'role' => $role->shortLabel(), 'name' => $user->name, 'limit' => $limit, 'count' => $count,
                ]),
            ]);
        }
    }
}
