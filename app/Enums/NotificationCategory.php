<?php

namespace App\Enums;

use App\Models\User;
use App\Notifications\ExpenseReturnedNotification;
use App\Notifications\ExpenseSubmittedNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;

enum NotificationCategory: string
{
    case General = 'general';
    case Receipt = 'receipt';

    private const RECEIPT_TYPES = [ExpenseSubmittedNotification::class, ExpenseReturnedNotification::class];

    public function label(): string
    {
        return $this === self::Receipt ? __('Receipt') : __('Notifikasi');
    }

    public static function forType(string $type): self
    {
        return in_array($type, self::RECEIPT_TYPES, true) ? self::Receipt : self::General;
    }

    /** Classify existing notifications as well as new ones, within the recipient's own inbox. */
    public function notifications(User $user): MorphMany
    {
        $query = $user->notifications();

        return $this === self::Receipt
            ? $query->whereIn('type', self::RECEIPT_TYPES)
            : $query->whereNotIn('type', self::RECEIPT_TYPES);
    }
}
