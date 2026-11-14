<?php

namespace App\Policies;

use App\Models\RecurringExpense;
use App\Models\User;

class RecurringExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $user->isManager() && $user->canAccessVilla($recurringExpense->villa_id);
    }

    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->update($user, $recurringExpense);
    }
}
