<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->string('hostex_stay_code', 150)->nullable()->unique();
            $table->string('hostex_date_basis', 20)->nullable();
            $table->string('hostex_void_reason', 30)->nullable();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('hostex_income_error', 60)->nullable();
            $table->timestamp('hostex_income_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['hostex_stay_code']);
            $table->dropColumn(['hostex_stay_code', 'hostex_date_basis', 'hostex_void_reason']);
        });
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['hostex_income_error', 'hostex_income_synced_at']));
    }
};
