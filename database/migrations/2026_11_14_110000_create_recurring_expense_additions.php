<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expense_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->string('label', 150);
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->index(['recurring_expense_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_additions');
    }
};
