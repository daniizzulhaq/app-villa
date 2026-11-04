<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements AuditableContract, HasLocalePreference
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes, Auditable;

    protected $fillable = ['name', 'username', 'email', 'password', 'phone', 'locale', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** Jangan simpan hash password & token ke audit log. */
    protected array $auditExclude = ['password', 'remember_token', 'last_login_at'];

    /** Cache per-request daftar villa yang boleh diakses. */
    protected ?array $accessibleVillaIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /* ---------------- Relasi ---------------- */

    /** Villa yang ditugaskan ke Staff. */
    public function assignedVillas(): BelongsToMany
    {
        return $this->belongsToMany(Villa::class, 'villa_user')->withTimestamps();
    }

    /** Villa yang dimiliki Owner. */
    public function ownedVillas(): HasMany
    {
        return $this->hasMany(Villa::class, 'owner_id');
    }

    /** Wilayah yang dipegang Manager. */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class)->withTimestamps();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /* ---------------- Role helper ---------------- */

    public function roleEnum(): ?UserRole
    {
        $name = $this->getRoleNames()->first();

        return $name ? UserRole::tryFrom($name) : null;
    }

    /** Master (manager utama): akses semua wilayah & villa. */
    public function isMaster(): bool
    {
        return $this->hasRole(UserRole::Master->value);
    }

    /**
     * Level manajemen: Master ATAU Manager wilayah.
     * Akses ke villa tertentu tetap dibatasi accessibleVillaIds() (Manager hanya wilayahnya).
     */
    public function isManager(): bool
    {
        return $this->hasAnyRole([UserRole::Master->value, UserRole::Manager->value]);
    }

    /** Manager wilayah (bukan Master). */
    public function isRegionalManager(): bool
    {
        return $this->hasRole(UserRole::Manager->value);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(UserRole::Owner->value);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(UserRole::Staff->value);
    }

    /**
     * ID villa yang boleh diakses user ini.
     * Master → null (artinya semua villa). Manager → villa di wilayah yang dipegangnya.
     * Dipakai oleh Villa::scopeAccessibleBy() dan semua Policy.
     */
    public function accessibleVillaIds(): ?array
    {
        if ($this->isMaster()) {
            return null;
        }

        return $this->accessibleVillaIdsCache ??= match (true) {
            $this->isRegionalManager() => Villa::withTrashed()->whereIn('region_id', $this->regions()->pluck('regions.id'))
                ->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $this->isOwner() => $this->ownedVillas()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $this->isStaff() => $this->assignedVillas()->pluck('villas.id')->map(fn ($id) => (int) $id)->all(),
            default => [],
        };
    }

    /** Batasi query apa pun yang punya kolom villa ke villa yang boleh diakses (Master: tanpa batas). */
    public function restrictToVillas(Builder|\Illuminate\Database\Eloquent\Relations\Relation $query, string $column = 'villa_id'): Builder|\Illuminate\Database\Eloquent\Relations\Relation
    {
        $ids = $this->accessibleVillaIds();

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    public function forgetVillaAccessCache(): static
    {
        $this->accessibleVillaIdsCache = null;

        return $this;
    }

    public function canAccessVilla(Villa|int|null $villa): bool
    {
        if ($villa === null) {
            return false;
        }
        $ids = $this->accessibleVillaIds();
        $id = (int) ($villa instanceof Villa ? $villa->id : $villa);

        return $ids === null || in_array($id, $ids, true);
    }

    /** Halaman pertama setelah login, sesuai role. Staff langsung ke Kalender. */
    public function homeUrl(): string
    {
        if ($this->isStaff() && Route::has('staff.calendar')) {
            return route('staff.calendar');
        }

        return route('dashboard');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    }

    /* ---------------- Scope ---------------- */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Notifikasi (email & in-app) dibuat dalam bahasa pilihan penerima. */
    public function preferredLocale(): ?string
    {
        return $this->locale ?: config('app.locale');
    }
}
