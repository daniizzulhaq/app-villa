<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

final class Notify
{
    /**
     * Kirim ke Master + Manager yang memegang wilayah villa tersebut.
     * Tanpa $villaId → hanya Master.
     */
    public static function managers(Notification $notification, ?int $villaId = null): void
    {
        $masters = User::role(UserRole::Master->value)->active()->get();
        $regional = collect();
        if ($villaId && ($regionId = Villa::whereKey($villaId)->value('region_id'))) {
            $regional = User::role(UserRole::Manager->value)->active()
                ->whereHas('regions', fn ($q) => $q->whereKey($regionId))->get();
        }

        self::send($masters->merge($regional)->unique('id')->values(), $notification);
    }

    public static function send(iterable $users, Notification $notification): void
    {
        try {
            NotificationFacade::send($users, $notification);
        } catch (Throwable $e) {
            // Notifikasi tidak boleh menggagalkan transaksi utama (mis. SMTP mati).
            report($e);
        }
    }

    public static function channels(?object $notifiable = null): array
    {
        // Akun tanpa email (login pakai username) cukup notifikasi di aplikasi.
        $hasEmail = $notifiable === null || filled($notifiable->email ?? null);

        return config('propertyme.notify_by_email') && $hasEmail ? ['database', 'mail'] : ['database'];
    }
}
