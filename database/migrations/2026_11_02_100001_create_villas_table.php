<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('location')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // Persentase fee 0.00–100.00
            $table->decimal('fee_percentage', 5, 2)->default(0);
            // gross | net_of_platform | custom  (lihat App\Enums\FeeBasis)
            $table->string('fee_basis', 30)->default('net_of_platform');
            $table->string('fee_basis_note')->nullable();
            $table->date('started_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Makassar');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            // Token rahasia untuk link ekspor iCal (dipakai di Tahap 2)
            $table->string('ical_token', 64)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('villa_fee_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->decimal('fee_percentage', 5, 2);
            $table->string('fee_basis', 30);
            $table->string('fee_basis_note')->nullable();
            $table->date('effective_from');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['villa_id', 'effective_from']);
        });

        Schema::create('villa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['villa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villa_user');
        Schema::dropIfExists('villa_fee_histories');
        Schema::dropIfExists('villas');
    }
};
