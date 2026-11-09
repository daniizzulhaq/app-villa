<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // 4 digit terakhir nomor HP tamu dari iCal Airbnb ("Phone Number (Last 4 Digits): 1234").
            $table->string('phone_last4', 4)->nullable()->after('guest_count');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('phone_last4');
        });
    }
};
