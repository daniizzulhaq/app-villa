<?php

namespace App\Notifications;

use App\Models\Expense;
use App\Support\Rupiah;

/** Ke Manager: Staff mengunggah / mengirim ulang nota. */
class ExpenseSubmittedNotification extends AppNotification
{
    public function __construct(public Expense $expense, public bool $resubmitted = false) {}

    protected function title(): string
    {
        return $this->resubmitted ? __('Nota dikirim ulang') : __('Nota baru menunggu pemeriksaan');
    }

    protected function body(): string
    {
        $e = $this->expense->loadMissing(['villa:id,name', 'creator:id,name', 'category:id,name']);

        return sprintf(__('%s mengunggah nota %s %s untuk %s (%s).'),
            $e->creator?->name ?? __('Staff'), __($e->category?->name ?? ''), Rupiah::format($e->amount), $e->villa?->name, $e->expense_date->translatedFormat('d M Y'));
    }

    protected function url(): string
    {
        return route('reviews.show', $this->expense);
    }

    protected function icon(): string
    {
        return 'bi-receipt';
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function actionText(): string
    {
        return __('Periksa nota');
    }
}
