<?php

namespace App\Models;

use App\Enums\FeeBasis;
use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Villa extends Model implements AuditableContract
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'name', 'location', 'photo', 'owner_id', 'region_id', 'fee_percentage', 'fee_basis', 'fee_basis_note',
        'started_at', 'timezone', 'is_active', 'notes',
    ];

    protected $hidden = ['ical_token'];

    protected array $auditExclude = ['ical_token'];

    protected function casts(): array
    {
        return [
            'fee_percentage' => 'decimal:2',
            'fee_basis' => FeeBasis::class,
            'started_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Villa $villa) {
            $villa->ical_token ??= Str::random(48);
            $villa->timezone ??= config('app.timezone');
        });
    }

    /* ---------------- Relasi ---------------- */

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Wilayah villa — menentukan Manager mana yang memegangnya. */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** Staff yang ditugaskan ke villa ini. */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'villa_user')->withTimestamps();
    }

    public function feeHistories(): HasMany
    {
        return $this->hasMany(VillaFeeHistory::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function latestFeeHistory(): HasOne
    {
        return $this->hasOne(VillaFeeHistory::class)->latestOfMany('effective_from');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(VillaChannel::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    /* ---------------- Scope ---------------- */

    /** Batasi query ke villa yang boleh diakses user (Manager: semua). */
    public function scopeAccessibleBy(Builder $q, User $user): Builder
    {
        $ids = $user->accessibleVillaIds();

        return $ids === null ? $q : $q->whereIn($this->qualifyColumn('id'), $ids);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /* ---------------- Helper ---------------- */

    /**
     * Fee yang berlaku pada tanggal tertentu (dari riwayat).
     * Dipakai saat membuat snapshot fee di pemasukan (Tahap 3).
     */
    public function feeOn(\DateTimeInterface|string $date): ?VillaFeeHistory
    {
        $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return VillaFeeHistory::query()
            ->where('villa_id', $this->id)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /** Contoh: "15% dari pendapatan setelah komisi platform" */
    public function feeLabel(): string
    {
        $basis = $this->fee_basis->label();

        return __(':percent dari :basis', [
            'percent' => Rupiah::percent($this->fee_percentage),
            'basis' => app()->getLocale() === 'de' ? $basis : mb_strtolower($basis), // Jerman: kata benda tetap kapital
        ]);
    }

    public function channelFor(string $platformSlug): ?VillaChannel
    {
        return $this->channels->first(fn (VillaChannel $c) => $c->platform?->slug === $platformSlug);
    }
}
