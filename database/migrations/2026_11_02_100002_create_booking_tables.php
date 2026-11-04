<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 60)->unique();
            $table->string('color', 9)->default('#64748B');
            $table->boolean('supports_ical')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('villa_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('booking_platforms')->cascadeOnDelete();
            $table->text('ical_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['villa_id', 'platform_id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('booking_platforms');
            // ical | manual | import
            $table->string('source', 20)->default('manual');
            $table->string('ical_uid')->nullable();
            $table->string('booking_ref', 100)->nullable();
            $table->string('guest_name', 150)->nullable();
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->time('arrival_time')->nullable();
            // needs_completion | confirmed | checked_in | checked_out | cancelled
            $table->string('status', 30)->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['villa_id', 'ical_uid']);
            $table->index(['villa_id', 'check_in', 'check_out']);
            $table->index(['villa_id', 'status']);
            $table->index('booking_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('villa_channels');
        Schema::dropIfExists('booking_platforms');
    }
};
