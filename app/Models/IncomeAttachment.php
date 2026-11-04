<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeAttachment extends Model
{
    protected $fillable = ['income_id', 'path', 'original_name', 'size'];

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }
}
