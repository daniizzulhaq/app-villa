<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wilayah: pengelompokan villa per area (mis. Ubud, Canggu).
 * Manager hanya mengakses villa di wilayah yang ditugaskan; Master mengakses semuanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('villas', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('owner_id')->constrained('regions')->nullOnDelete();
        });

        // Manager ↔ wilayah (satu manager bisa beberapa wilayah, satu wilayah bisa beberapa manager)
        Schema::create('region_user', function (Blueprint $table) {
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['region_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_user');
        Schema::table('villas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });
        Schema::dropIfExists('regions');
    }
};
