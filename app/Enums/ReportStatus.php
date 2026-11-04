<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Final => __('Final'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Final => 'info',
        };
    }
}
