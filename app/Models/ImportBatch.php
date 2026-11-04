<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = ['type', 'file_name', 'rows_total', 'rows_imported', 'rows_duplicate', 'rows_failed', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
