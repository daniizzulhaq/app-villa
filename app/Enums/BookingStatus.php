<?php

namespace App\Enums;

enum BookingStatus: string
{
    case NeedsCompletion = 'needs_completion';
    case Confirmed = 'confirmed';
    case PendingPayments = 'pending_payments';
    case OnHold = 'on_hold';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NeedsCompletion, self::OnHold => __('On Hold'),
            self::Confirmed, self::CheckedIn, self::CheckedOut => __('Confirmed'),
            self::PendingPayments => __('Pending Payments'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** Varian badge di design system. */
    public function badge(): string
    {
        return match ($this) {
            self::NeedsCompletion, self::PendingPayments => 'warning',
            self::Confirmed, self::CheckedIn, self::CheckedOut => 'primary',
            self::OnHold => 'neutral',
            self::Cancelled => 'danger',
        };
    }

    /** Choices for forms and filters; legacy sync states remain readable. */
    public static function selectable(): array
    {
        return [self::Confirmed, self::PendingPayments, self::OnHold, self::Cancelled];
    }

    public function selectableStatus(): self
    {
        return match ($this) {
            self::NeedsCompletion => self::OnHold,
            self::CheckedIn, self::CheckedOut => self::Confirmed,
            default => $this,
        };
    }

    public function filterValues(): array
    {
        return match ($this) {
            self::Confirmed => [self::Confirmed->value, self::CheckedIn->value, self::CheckedOut->value],
            self::OnHold => [self::OnHold->value, self::NeedsCompletion->value],
            default => [$this->value],
        };
    }

    /** Status yang menempati kalender (dipakai validasi bentrok). */
    public static function occupying(): array
    {
        return [self::NeedsCompletion->value, self::Confirmed->value, self::PendingPayments->value, self::OnHold->value, self::CheckedIn->value, self::CheckedOut->value];
    }
}
