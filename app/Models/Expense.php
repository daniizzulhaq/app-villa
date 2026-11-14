<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Expense extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'villa_id', 'category_id', 'expense_date', 'description', 'vendor', 'amount', 'payment_method',
        'notes', 'status', 'return_reason', 'created_by', 'checked_by', 'checked_at',
        'recurring_expense_id', 'recurring_period', 'base_amount', 'additions',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'recurring_period' => 'date',
            'additions' => 'array',
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'checked_at' => 'datetime',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class);
    }

    /** Hanya pengeluaran Valid yang masuk perhitungan keuangan. */
    public function scopeValid(Builder $q): Builder
    {
        return $q->where('status', ExpenseStatus::Valid->value);
    }
}
