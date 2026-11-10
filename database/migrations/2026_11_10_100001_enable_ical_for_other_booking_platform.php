<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Pertahankan ID platform dan reservasi Lainnya yang sudah ada di hosting.
        DB::table('booking_platforms')->where('slug', 'lainnya')->update(['supports_ical' => true]);
    }

    public function down(): void
    {
        DB::table('booking_platforms')->where('slug', 'lainnya')->update(['supports_ical' => false]);
    }
};
