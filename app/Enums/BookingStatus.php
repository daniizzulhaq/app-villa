<?php

namespace App\Enums;

enum BookingStatus: string
{
    case NeedsCompletion = 'needs_completion';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NeedsCompletion => __('Perlu dilengkapi'),
            self::Confirmed => __('Confirmed'),
            self::CheckedIn => __('Checked-in'),
            self::CheckedOut => __('Checked-out'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** Varian badge di design system. */
    public function badge(): string
    {
        return match ($this) {
            self::NeedsCompletion => 'warning',
            self::Confirmed => 'primary',
            self::CheckedIn => 'success',
            self::CheckedOut => 'neutral',
            self::Cancelled => 'danger',
        };
    }

    /** Status yang menempati kalender (dipakai validasi bentrok). */
    public static function occupying(): array
    {
        return [self::NeedsCompletion->value, self::Confirmed->value, self::CheckedIn->value, self::CheckedOut->value];
    }
}
