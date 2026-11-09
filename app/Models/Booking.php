<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Booking extends Model implements AuditableContract
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'villa_id', 'platform_id', 'source', 'ical_uid', 'booking_ref', 'guest_name', 'guest_count', 'phone_last4',
        'check_in', 'check_out', 'arrival_time', 'total_price', 'price_source', 'status', 'cancelled_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'cancelled_at' => 'datetime',
            'status' => BookingStatus::class,
            'guest_count' => 'integer',
            'total_price' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /** Booking hasil sinkron iCal: tanggalnya milik platform, tidak boleh diubah manual. */
    public function isFromPlatformSync(): bool
    {
        return $this->source === 'ical';
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
