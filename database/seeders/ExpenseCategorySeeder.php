<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /** 16 kategori bawaan. Kategori custom ditambahkan Manager lewat Pengaturan. */
    public const DEFAULTS = [
        ['Housekeeping & Cleaning', 'bi-stars'],
        ['Gaji Staff', 'bi-person-badge'],
        ['Perawatan Kolam', 'bi-water'],
        ['Perawatan Taman', 'bi-flower2'],
        ['Laundry & Linen', 'bi-basket'],
        ['Listrik', 'bi-lightning-charge'],
        ['Air', 'bi-droplet'],
        ['Internet', 'bi-wifi'],
        ['Iuran Banjar', 'bi-people'],
        ['Perbaikan & Perawatan', 'bi-tools'],
        ['AC', 'bi-snow'],
        ['Perlengkapan Tamu', 'bi-bag-heart'],
        ['Transportasi', 'bi-scooter'],
        ['Marketing', 'bi-megaphone'],
        ['Peralatan Villa', 'bi-lamp'],
        ['Lainnya', 'bi-three-dots'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => [$name, $icon]) {
            ExpenseCategory::updateOrCreate(
                ['name' => $name],
                ['icon' => $icon, 'is_default' => true, 'is_active' => true, 'sort_order' => $i + 1],
            );
        }
    }
}
