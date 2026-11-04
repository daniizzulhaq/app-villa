<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class MonthlyReport extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['villa_id', 'year', 'month', 'status', 'finalized_at', 'finalized_by', 'revision', 'unlocked_at', 'unlocked_by', 'unlock_reason'];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'finalized_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'year' => 'integer',
            'month' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Apakah bulan dari tanggal transaksi ini sudah dikunci (laporan Final)?
     * payment_date / expense_date disimpan sebagai tanggal lokal villa, jadi cukup ambil Y-n.
     */
    public static function isLocked(int $villaId, \DateTimeInterface|string|null $date): bool
    {
        if (! $date) {
            return false;
        }
        $d = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date);

        return static::query()
            ->where('villa_id', $villaId)
            ->where('year', (int) $d->format('Y'))
            ->where('month', (int) $d->format('n'))
            ->where('status', ReportStatus::Final->value)
            ->exists();
    }
}
