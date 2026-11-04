<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Tunai'),
            self::Transfer => __('Transfer Bank'),
            self::Qris => __('QRIS / E-wallet'),
            self::Card => __('Kartu Debit/Kredit'),
            self::Other => __('Lainnya'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
