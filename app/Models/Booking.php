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
    use Auditable, SoftDeletes;

    protected array $auditExclude = ['hostex_income_synced_at'];

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
            'hostex_rate_amount' => 'decimal:2',
            'hostex_commission_amount' => 'decimal:2',
            'hostex_income_synced_at' => 'datetime',
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

    /** Dates on externally synchronized bookings belong to the source platform. */
    public function isFromPlatformSync(): bool
    {
        return in_array($this->source, ['ical', 'hostex'], true);
    }

    public function hostexIncomeIssueLabel(): string
    {
        return __(match ($this->hostex_income_error) {
            'missing_amounts' => 'Hostex booking value or commission is incomplete.',
            'currency' => 'Hostex income needs an IDR amount and commission.',
            'invalid_amounts' => 'Hostex commission exceeds the booking value.',
            'locked' => 'Unlock the final report before updating automatic income.',
            'custom_fee' => 'Custom management fees require a manual income entry.',
            'deleted_income' => 'A deleted income entry needs review before automatic recording.',
            'unlinked_income' => 'Existing income matches this reference. Link it to the booking before automatic recording.',
            'allocation' => 'The existing Hostex income belongs to a different booking or villa.',
            'date_basis' => 'Choose check-in or check-out for the Hostex reporting date.',
            default => 'Automatic Hostex income is waiting for synchronization.',
        });
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
