<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Menunggu pemeriksaan'),
            self::Valid => __('Valid'),
            self::Returned => __('Dikembalikan'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Valid => 'success',
            self::Returned => 'danger',
        };
    }

    /** Status yang masih boleh diedit oleh Staff pembuatnya. */
    public function isEditableByStaff(): bool
    {
        return in_array($this, [self::Pending, self::Returned], true);
    }
}
