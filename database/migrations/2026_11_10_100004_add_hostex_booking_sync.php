<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('hostex_stay_code', 150)->nullable()->unique();
            $table->string('hostex_status', 30)->nullable();
            $table->string('hostex_rate_currency', 10)->nullable();
            $table->decimal('hostex_rate_amount', 15, 2)->nullable();
            $table->string('hostex_commission_currency', 10)->nullable();
            $table->decimal('hostex_commission_amount', 15, 2)->nullable();
        });
        Schema::table('villas', function (Blueprint $table) {
            $table->timestamp('hostex_last_synced_at')->nullable();
            $table->timestamp('hostex_last_success_at')->nullable();
            $table->text('hostex_last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['hostex_stay_code']);
            $table->dropColumn(['hostex_stay_code', 'hostex_status', 'hostex_rate_currency', 'hostex_rate_amount', 'hostex_commission_currency', 'hostex_commission_amount']);
        });
        Schema::table('villas', function (Blueprint $table) {
            $table->dropColumn(['hostex_last_synced_at', 'hostex_last_success_at', 'hostex_last_error']);
        });
    }
};
