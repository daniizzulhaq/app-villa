<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Harga total reservasi (yang dibayar tamu). Diisi dari iCal bila feed menyertakan harga, atau manual.
            $table->decimal('total_price', 15, 2)->nullable()->after('arrival_time');
            // ical | manual — asal nilai total_price, agar sinkron tidak menimpa harga yang diisi manual.
            $table->string('price_source', 10)->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'price_source']);
        });
    }
};
