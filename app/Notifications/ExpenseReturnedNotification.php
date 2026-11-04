<?php

namespace App\Notifications;

use App\Models\Expense;
use App\Support\Rupiah;

/** Ke Staff: nota dikembalikan Manager beserta alasannya. */
class ExpenseReturnedNotification extends AppNotification
{
    public function __construct(public Expense $expense) {}

    protected function title(): string
    {
        return __('Nota dikembalikan — perlu diperbaiki');
    }

    protected function body(): string
    {
        $e = $this->expense->loadMissing('villa:id,name');

        return sprintf(__('Nota %s (%s, %s) dikembalikan. Alasan: "%s". Perbaiki lalu kirim ulang.'),
            $e->description, Rupiah::format($e->amount), $e->villa?->name, $e->return_reason);
    }

    protected function url(): string
    {
        return route('expenses.edit', $this->expense);
    }

    protected function icon(): string
    {
        return 'bi-arrow-counterclockwise';
    }

    protected function tone(): string
    {
        return 'danger';
    }

    protected function actionText(): string
    {
        return __('Perbaiki nota');
    }
}
