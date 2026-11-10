<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->unsignedBigInteger('beds24_property_id')->nullable();
            $table->unsignedBigInteger('beds24_room_id')->nullable();
            $table->unique(['beds24_property_id', 'beds24_room_id']);
        });
    }

    public function down(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->dropUnique(['beds24_property_id', 'beds24_room_id']);
            $table->dropColumn(['beds24_property_id', 'beds24_room_id']);
        });
    }
};
