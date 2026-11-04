<?php

namespace App\Models;

use App\Enums\FeeBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Income extends Model implements AuditableContract
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'booking_id', 'villa_id', 'platform_id', 'booking_ref', 'payment_date', 'gross_amount', 'platform_fee',
        'other_deduction', 'net_revenue', 'is_net_of_platform', 'fee_percentage', 'fee_basis', 'fee_base_amount',
        'fee_amount', 'notes', 'import_batch_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'gross_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_revenue' => 'decimal:2',
            'fee_percentage' => 'decimal:2',
            'fee_base_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'is_net_of_platform' => 'boolean',
            'fee_basis' => FeeBasis::class,
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(BookingPlatform::class, 'platform_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncomeAttachment::class);
    }
}
