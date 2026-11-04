<?php

namespace App\Enums;

enum FeeBasis: string
{
    case Gross = 'gross';
    case NetOfPlatform = 'net_of_platform';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Gross => __('Gross booking income'),
            self::NetOfPlatform => __('Pendapatan setelah komisi platform'),
            self::Custom => __('Basis lain (kesepakatan)'),
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Gross => __('Fee dihitung dari total pembayaran tamu, sebelum komisi platform dipotong.'),
            self::NetOfPlatform => __('Fee dihitung setelah komisi platform & potongan lain dikurangi. Paling umum dipakai.'),
            self::Custom => __('Nominal basis fee diisi Manager di setiap pemasukan sesuai kesepakatan dengan owner.'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $b) => [$b->value => $b->label()])->all();
    }
}
