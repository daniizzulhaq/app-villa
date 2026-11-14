<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RecurringExpense extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'villa_id', 'category_id', 'description', 'vendor', 'amount', 'payment_method', 'notes',
        'day_of_month', 'starts_on', 'ends_on', 'next_period', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'day_of_month' => 'integer', 'is_active' => 'boolean',
            'starts_on' => 'date', 'ends_on' => 'date', 'next_period' => 'date',
            'payment_method' => PaymentMethod::class,
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
}
