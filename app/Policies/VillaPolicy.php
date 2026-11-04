<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Villa;

/**
 * Master: semua villa. Manager: villa di wilayahnya (lihat & kelola operasional).
 * Owner: villa miliknya. Staff: villa yang ditugaskan. Daftar difilter lewat scopeAccessibleBy.
 */
class VillaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Villa $villa): bool
    {
        return $user->canAccessVilla($villa);
    }

    /** Tambah villa baru — khusus Master. */
    public function create(User $user): bool
    {
        return $user->isMaster();
    }

    /** Ubah data villa (info, link iCal, staff): Master, atau Manager untuk villa di wilayahnya. */
    public function update(User $user, Villa $villa): bool
    {
        return $user->isManager() && $user->canAccessVilla($villa);
    }

    /** Mengatur % management fee & basis fee — khusus Master (menyangkut uang owner). */
    public function manageFee(User $user, ?Villa $villa = null): bool
    {
        return $user->isMaster();
    }

    /** Menentukan owner & wilayah villa — khusus Master. */
    public function manageOwnership(User $user, ?Villa $villa = null): bool
    {
        return $user->isMaster();
    }

    public function assignStaff(User $user, Villa $villa): bool
    {
        return $user->isManager() && $user->canAccessVilla($villa);
    }

    /** Hapus / nonaktifkan villa — khusus Master. */
    public function delete(User $user, Villa $villa): bool
    {
        return $user->isMaster();
    }

    /** Lihat angka keuangan villa (pendapatan, fee, laba). Staff tidak boleh. */
    public function viewFinance(User $user, Villa $villa): bool
    {
        return ($user->isManager() || $user->isOwner()) && $user->canAccessVilla($villa);
    }
}
