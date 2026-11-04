<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->timestamp('unlocked_at')->nullable()->after('revision');
            $table->foreignId('unlocked_by')->nullable()->after('unlocked_at')->constrained('users')->nullOnDelete();
            $table->string('unlock_reason')->nullable()->after('unlocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unlocked_by');
            $table->dropColumn(['unlocked_at', 'unlock_reason']);
        });
    }
};
