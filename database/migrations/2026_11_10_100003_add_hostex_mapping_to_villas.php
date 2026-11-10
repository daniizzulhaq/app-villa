<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->unsignedBigInteger('hostex_property_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->dropUnique(['hostex_property_id']);
            $table->dropColumn('hostex_property_id');
        });
    }
};
