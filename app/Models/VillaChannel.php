<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VillaChannel extends Model
{
    protected $fillable = ['villa_id', 'platform_id', 'ical_url', 'is_active', 'last_synced_at', 'last_success_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(BookingPlatform::class, 'platform_id');
    }
}
