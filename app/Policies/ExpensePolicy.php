<?php

namespace App\Policies;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\MonthlyReport;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // query dibatasi per role di controller
    }

    /**
     * Master: semua. Manager: villa di wilayahnya. Staff: hanya miliknya di villa yang ditugaskan.
     * Owner: hanya villa miliknya DAN hanya yang sudah diperiksa (Valid).
     */
    public function view(User $user, Expense $expense): bool
    {
        if (! $user->canAccessVilla($expense->villa_id)) {
            return false;
        }
        if ($user->isManager()) {
            return true;
        }
        if ($user->isStaff()) {
            return (int) $expense->created_by === (int) $user->id;
        }

        return $user->isOwner() && $expense->status === ExpenseStatus::Valid;
    }

    public function create(User $user): bool
    {
        return $user->isManager() || $user->isStaff();
    }

    /** Staff & Manager hanya boleh input untuk villa yang bisa diaksesnya. */
    public function createForVilla(User $user, int $villaId): bool
    {
        return $this->create($user) && $user->canAccessVilla($villaId);
    }

    public function update(User $user, Expense $expense): bool
    {
        if (MonthlyReport::isLocked($expense->villa_id, $expense->getOriginal('expense_date'))) {
            return false;
        }
        if ($user->isManager()) {
            return $user->canAccessVilla($expense->villa_id);
        }

        return $user->isStaff()
            && (int) $expense->created_by === (int) $user->id
            && $user->canAccessVilla($expense->villa_id)
            && $expense->status->isEditableByStaff();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }

    /** Pemeriksaan nota (Valid / Dikembalikan) — Master, atau Manager untuk villa di wilayahnya. */
    public function review(User $user, ?Expense $expense = null): bool
    {
        if (! $user->isManager()) {
            return false;
        }

        return $expense === null
            || ($user->canAccessVilla($expense->villa_id) && ! MonthlyReport::isLocked($expense->villa_id, $expense->expense_date));
    }

    /** Akses file foto nota mengikuti hak lihat pengeluarannya. */
    public function viewReceipt(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense);
    }
}
