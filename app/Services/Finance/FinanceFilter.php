<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\Villa;
use Illuminate\Support\Carbon;

/** Filter periode + villa + sumber booking. Tanggal = tanggal lokal villa (payment_date / expense_date). */
final class FinanceFilter
{
    /**
     * @param int[]|null $villaIds null = semua villa
     * @param array<int, string[]>|null $monthScope batasi ke pasangan villa → bulan ('Y-m') tertentu,
     *        dipakai agar Owner hanya melihat bulan yang laporannya sudah Final.
     */
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?array $villaIds = null,
        public readonly ?int $platformId = null,
        public readonly ?array $monthScope = null,
    ) {}

    public function withMonthScope(?array $scope): self
    {
        return new self($this->from, $this->to, $this->villaIds, $this->platformId, $scope);
    }

    /** Batasi ke villa yang boleh diakses user; $villaId opsional untuk satu villa. */
    public static function for(User $user, Carbon $from, Carbon $to, ?int $villaId = null, ?int $platformId = null): self
    {
        $allowed = $user->accessibleVillaIds(); // null = manager (semua)
        if ($villaId) {
            $ids = ($allowed === null || in_array($villaId, $allowed, true)) ? [$villaId] : [];
        } else {
            $ids = $allowed;
        }

        return new self($from->copy()->startOfDay(), $to->copy()->startOfDay(), $ids, $platformId);
    }

    public static function month(int $year, int $month, ?array $villaIds = null, ?int $platformId = null): self
    {
        $from = Carbon::create($year, $month, 1);

        return new self($from, $from->copy()->endOfMonth()->startOfDay(), $villaIds, $platformId);
    }

    public function withPeriod(Carbon $from, Carbon $to): self
    {
        return new self($from->copy()->startOfDay(), $to->copy()->startOfDay(), $this->villaIds, $this->platformId, $this->monthScope);
    }

    public function withVillas(?array $ids): self
    {
        return new self($this->from, $this->to, $ids, $this->platformId, $this->monthScope);
    }

    public function villas()
    {
        return Villa::query()->when($this->villaIds !== null, fn ($q) => $q->whereIn('id', $this->villaIds ?: [0]));
    }
}
