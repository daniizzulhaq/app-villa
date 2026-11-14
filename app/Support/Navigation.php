<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\ExpenseStatus;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Satu definisi menu per role, dipakai sidebar (desktop), bottom nav & offcanvas (HP).
 * Item yang route-nya belum dibuat (tahap berikutnya) tampil non-aktif dengan label "Segera".
 */
final class Navigation
{
    /** @return array<int, array{group:?string, items: array}> */
    public static function sidebar(User $user): array
    {
        return match (true) {
            $user->isManager() => [
                ['group' => null, 'items' => [
                    self::item(__('Dashboard'), 'bi-grid-1x2', 'dashboard'),
                ]],
                ['group' => __('Operasional'), 'items' => [
                    self::item(__('Villa'), 'bi-houses', 'villas.index', 'villas.*'),
                    self::item(__('Reservasi & Kalender'), 'bi-calendar3', 'bookings.calendar', 'bookings.*'),
                    self::item(__('Status Sinkronisasi'), 'bi-arrow-repeat', 'sync.index', 'sync.*'),
                ]],
                ['group' => __('Keuangan'), 'items' => [
                    self::item(__('Pemasukan'), 'bi-box-arrow-in-down', 'incomes.index', 'incomes.*'),
                    self::item(__('Pengeluaran'), 'bi-receipt', 'expenses.index', 'expenses.*'),
                    self::item(__('Pengeluaran Rutin'), 'bi-arrow-repeat', 'recurring-expenses.index', 'recurring-expenses.*'),
                    self::item(__('Pemeriksaan Nota'), 'bi-patch-check', 'reviews.index', 'reviews.*'),
                    self::item(__('Laporan'), 'bi-file-earmark-text', 'reports.index', 'reports.*'),
                    self::item(__('Grafik & Analisis'), 'bi-bar-chart-line', 'analytics.index', 'analytics.*'),
                ]],
                ['group' => __('Sistem'), 'items' => $user->isMaster() ? [
                    self::item(__('Wilayah'), 'bi-geo-alt', 'regions.index', 'regions.*'),
                    self::item(__('User & Akses'), 'bi-people', 'users.index', 'users.*'),
                    self::item(__('Audit Log'), 'bi-journal-text', 'audits.index', 'audits.*'),
                    self::item(__('Pengaturan'), 'bi-gear', 'settings.index', 'settings.*'),
                ] : [
                    self::item(__('Staff'), 'bi-people', 'users.index', 'users.*'),
                    self::item(__('Audit Log'), 'bi-journal-text', 'audits.index', 'audits.*'),
                ]],
            ],
            $user->isOwner() => [
                ['group' => null, 'items' => [
                    self::item(__('Dashboard'), 'bi-grid-1x2', 'dashboard'),
                    self::item(__('Reservasi & Kalender'), 'bi-calendar3', 'bookings.calendar', 'bookings.*'),
                ]],
                ['group' => __('Keuangan'), 'items' => [
                    self::item(__('Pemasukan'), 'bi-box-arrow-in-down', 'incomes.index', 'incomes.*'),
                    self::item(__('Pengeluaran & Nota'), 'bi-receipt', 'expenses.index', 'expenses.*'),
                    self::item(__('Laporan'), 'bi-file-earmark-text', 'reports.index', 'reports.*'),
                    self::item(__('Grafik'), 'bi-bar-chart-line', 'analytics.index', 'analytics.*'),
                ]],
            ],
            default => [ // Staff
                ['group' => null, 'items' => [
                    self::item(__('Kalender Reservasi'), 'bi-calendar3', 'staff.calendar', 'staff.calendar', fallback: 'dashboard'),
                    self::item(__('Upload Nota'), 'bi-camera', 'expenses.create'),
                    self::item(__('Riwayat Aktivitas'), 'bi-clock-history', 'staff.activity', 'staff.activity'),
                    self::item(__('Profil'), 'bi-person-circle', 'profile.edit', 'profile.*'),
                ]],
            ],
        };
    }

    /** Maksimal 5 item di bottom nav HP. Item "Menu" membuka offcanvas berisi semua menu. */
    public static function bottom(User $user): array
    {
        return match (true) {
            $user->isManager() => [
                self::item(__('Dashboard'), 'bi-grid-1x2', 'dashboard'),
                self::item(__('Reservasi'), 'bi-calendar3', 'bookings.calendar', 'bookings.*'),
                self::item(__('Nota'), 'bi-patch-check', 'reviews.index', 'reviews.*'),
                self::item(__('Villa'), 'bi-houses', 'villas.index', 'villas.*'),
                ['menu' => true, 'label' => __('Menu'), 'icon' => 'bi-list'],
            ],
            $user->isOwner() => [
                self::item(__('Dashboard'), 'bi-grid-1x2', 'dashboard'),
                self::item(__('Reservasi'), 'bi-calendar3', 'bookings.calendar', 'bookings.*'),
                self::item(__('Pengeluaran'), 'bi-receipt', 'expenses.index', 'expenses.*'),
                self::item(__('Laporan'), 'bi-file-earmark-text', 'reports.index', 'reports.*'),
                ['menu' => true, 'label' => __('Menu'), 'icon' => 'bi-list'],
            ],
            default => [
                self::item(__('Kalender'), 'bi-calendar3', 'staff.calendar', 'staff.calendar', fallback: 'dashboard'),
                self::item(__('Upload Nota'), 'bi-camera', 'expenses.create'),
                self::item(__('Riwayat'), 'bi-clock-history', 'staff.activity', 'staff.activity'),
                self::item(__('Profil'), 'bi-person-circle', 'profile.edit', 'profile.*'),
            ],
        };
    }

    private static function item(string $label, string $icon, string $route, ?string $active = null, ?string $fallback = null): array
    {
        $exists = Route::has($route);
        if (! $exists && $fallback && Route::has($fallback)) {
            // Contoh: sebelum Tahap 2, "Kalender" Staff mengarah ke beranda Staff.
            $route = $fallback;
            $active = $fallback;
            $exists = true;
        }

        return [
            'label' => $label,
            'icon' => $icon,
            'url' => $exists ? route($route) : null,
            'active' => $exists && request()->routeIs($active ?? $route),
            'available' => $exists,
            'count' => self::counts()[$route] ?? 0,
        ];
    }

    /** Angka badge di menu: nota menunggu (Manager), nota dikembalikan (Staff). Dihitung sekali per request. */
    private static function counts(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $user = auth()->user();
        $cache = [];
        if ($user?->isManager()) {
            // Manager wilayah: hanya villa di wilayahnya
            $cache['reviews.index'] = $user->restrictToVillas(Expense::query())->where('status', ExpenseStatus::Pending->value)->count();
            $cache['bookings.calendar'] = $user->restrictToVillas(Booking::query())->where('status', BookingStatus::NeedsCompletion->value)->count();
        } elseif ($user?->isStaff()) {
            $cache['staff.activity'] = Expense::where('created_by', $user->id)->where('status', ExpenseStatus::Returned->value)->count();
        }

        return $cache;
    }
}
