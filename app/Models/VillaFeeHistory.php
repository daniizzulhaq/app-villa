<?php

namespace App\Models;

use App\Enums\FeeBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class VillaFeeHistory extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['villa_id', 'fee_percentage', 'fee_basis', 'fee_basis_note', 'effective_from', 'changed_by'];

    protected function casts(): array
    {
        return [
            'fee_percentage' => 'decimal:2',
            'fee_basis' => FeeBasis::class,
            'effective_from' => 'date',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
