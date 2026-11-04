<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseReceipt extends Model
{
    protected $fillable = ['expense_id', 'path', 'original_name', 'size'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
