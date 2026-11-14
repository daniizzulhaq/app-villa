<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->string('description');
            $table->string('vendor', 150)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('day_of_month');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_period');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('recurring_expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('recurring_period')->nullable();
            $table->decimal('base_amount', 15, 2)->nullable();
            $table->json('additions')->nullable();
            // Includes soft-deleted transactions: deleting one month must not recreate it.
            $table->unique(['recurring_expense_id', 'recurring_period'], 'expenses_recurring_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_recurring_period_unique');
            $table->dropConstrainedForeignId('recurring_expense_id');
            $table->dropColumn(['recurring_period', 'base_amount', 'additions']);
        });
        Schema::dropIfExists('recurring_expenses');
    }
};
