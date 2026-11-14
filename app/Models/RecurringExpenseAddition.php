<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RecurringExpenseAddition extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['recurring_expense_id', 'period', 'label', 'amount', 'note', 'created_by', 'applied_expense_id'];

    protected function casts(): array
    {
        return ['period' => 'date', 'amount' => 'decimal:2'];
    }
}
