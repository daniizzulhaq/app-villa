<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Master mengelola semua akun. Manager wilayah hanya mengelola akun Staff
 * yang bertugas di villa wilayahnya (atau Staff yang belum ditugaskan ke mana pun).
 * Staff & Owner hanya mengubah profilnya sendiri (ProfileController).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, User $model): bool
    {
        return $this->manages($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Role yang boleh diberikan saat membuat / mengubah akun: Manager wilayah hanya Staff. */
    public function assignRole(User $user, UserRole $role): bool
    {
        return $user->isMaster() || $role === UserRole::Staff;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is($model) ? $user->isMaster() : $this->manages($user, $model);
    }

    /** Tidak bisa menonaktifkan / menghapus akun sendiri (mencegah terkunci dari sistem). */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->isNot($model) && $this->manages($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isNot($model) && $this->manages($user, $model);
    }

    private function manages(User $user, User $model): bool
    {
        if ($user->isMaster()) {
            return true;
        }
        if (! $user->isRegionalManager() || ! $model->isStaff()) {
            return false;
        }
        $staffVillas = $model->assignedVillas()->pluck('villas.id')->map(fn ($id) => (int) $id)->all();

        // Staff yang belum ditugaskan boleh diambil; selain itu minimal satu villanya ada di wilayah Manager.
        return $staffVillas === [] || array_intersect($staffVillas, $user->accessibleVillaIds()) !== [];
    }
}
