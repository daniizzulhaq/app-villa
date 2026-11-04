<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // income | booking
            $table->string('file_name');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_duplicate')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('villa_id')->constrained();
            $table->foreignId('platform_id')->constrained('booking_platforms');
            $table->string('booking_ref', 100)->nullable();
            $table->date('payment_date');
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('other_deduction', 15, 2)->default(0);
            $table->decimal('net_revenue', 15, 2);
            $table->boolean('is_net_of_platform')->default(false);
            // Snapshot fee saat transaksi dicatat — tidak berubah walau % fee villa diubah
            $table->decimal('fee_percentage', 5, 2);
            $table->string('fee_basis', 30);
            $table->decimal('fee_base_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['villa_id', 'payment_date']);
            $table->unique(['villa_id', 'platform_id', 'booking_ref']);
        });

        Schema::create('income_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('icon', 50)->default('bi-tag');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->date('expense_date');
            $table->string('description');
            $table->string('vendor', 150)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30)->nullable(); // cash | transfer | card | qris | other
            $table->text('notes')->nullable();
            // pending | valid | returned
            $table->string('status', 20)->default('pending');
            $table->text('return_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['villa_id', 'expense_date', 'status']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('expense_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('draft'); // draft | final
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();

            $table->unique(['villa_id', 'year', 'month']);
            $table->index(['status', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('expense_receipts');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('income_attachments');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('import_batches');
    }
};
