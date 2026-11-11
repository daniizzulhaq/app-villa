<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_platforms')->where('slug', 'direct')->update(['color' => '#6B7280']);
    }

    public function down(): void
    {
        DB::table('booking_platforms')->where('slug', 'direct')->where('color', '#6B7280')->update(['color' => '#0E8F63']);
    }
};
