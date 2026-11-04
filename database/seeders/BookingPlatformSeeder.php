<?php

namespace Database\Seeders;

use App\Models\BookingPlatform;
use Illuminate\Database\Seeder;

class BookingPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['slug' => BookingPlatform::AIRBNB, 'name' => 'Airbnb', 'color' => '#E0484E', 'supports_ical' => true, 'sort_order' => 1],
            ['slug' => BookingPlatform::BOOKING_COM, 'name' => 'Booking.com', 'color' => '#1A4FA0', 'supports_ical' => true, 'sort_order' => 2],
            ['slug' => BookingPlatform::DIRECT, 'name' => 'Direct Booking', 'color' => '#0E8F63', 'supports_ical' => false, 'sort_order' => 3],
            ['slug' => BookingPlatform::OTHER, 'name' => 'Lainnya', 'color' => '#7C6F9B', 'supports_ical' => false, 'sort_order' => 4],
        ];

        foreach ($platforms as $p) {
            BookingPlatform::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
