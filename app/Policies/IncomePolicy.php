<?php

namespace App\Policies;

use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\User;

class IncomePolicy
{
    /** Staff tidak melihat pendapatan sama sekali. */
    public function viewAny(User $user): bool
    {
        return $user->isManager() || $user->isOwner();
    }

    public function view(User $user, Income $income): bool
    {
        return ($user->isManager() || $user->isOwner()) && $user->canAccessVilla($income->villa_id);
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Hanya villa yang bisa diakses; bulan yang sudah Final terkunci — Master harus membuka kunci dulu. */
    public function update(User $user, Income $income): bool
    {
        return $user->isManager()
            && ! $income->isFromHostex()
            && $user->canAccessVilla($income->villa_id)
            && ! MonthlyReport::isLocked($income->villa_id, $income->getOriginal('payment_date'));
    }

    public function delete(User $user, Income $income): bool
    {
        return $this->update($user, $income);
    }

    public function import(User $user): bool
    {
        return $user->isManager();
    }
}
