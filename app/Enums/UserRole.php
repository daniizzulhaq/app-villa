<?php

namespace App\Enums;

enum UserRole: string
{
    case Master = 'master';
    case Manager = 'manager';
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Master => __('Master (Manager Utama)'),
            self::Manager => __('Manager Wilayah'),
            self::Owner => __('Owner (Pemilik Villa)'),
            self::Staff => __('Staff Operasional'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Master => __('Master'),
            self::Manager => __('Manager'),
            self::Owner => __('Owner'),
            self::Staff => __('Staff'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Master => __('Akses penuh ke semua wilayah & villa, termasuk akun Manager, % fee, dan pengaturan.'),
            self::Manager => __('Mengelola villa di wilayah yang diberikan: reservasi, keuangan, laporan, dan akun Staff.'),
            self::Owner => __('Hanya melihat villa miliknya & laporan yang sudah diperiksa.'),
            self::Staff => __('Kalender villa yang ditugaskan & upload nota pengeluaran.'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Master => 'danger',
            self::Manager => 'primary',
            self::Owner => 'info',
            self::Staff => 'neutral',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
