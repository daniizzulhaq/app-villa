<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data dasar yang WAJIB ada di produksi. Tidak berisi data dummy.
 * Data contoh untuk demo: php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            BookingPlatformSeeder::class,
            ExpenseCategorySeeder::class,
            ManagerSeeder::class,
        ]);
    }
}
