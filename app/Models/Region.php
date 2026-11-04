<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** Wilayah — sekelompok villa yang dipegang satu atau beberapa Manager. */
class Region extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['name', 'description'];

    public function villas(): HasMany
    {
        return $this->hasMany(Villa::class);
    }

    /** Manager yang memegang wilayah ini. */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
