<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jalankan dari Cron Job cPanel tiap menit:
|   * * * * * cd /home/USER/propertyme && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
*/

// Sinkron iCal semua platform yang terhubung tiap 30 menit
Schedule::command('propertyme:sync-ical')->everyThirtyMinutes()->withoutOverlapping(25);

// Hostex reporting pull; enable only after credentials and villa mappings are verified.
Schedule::command('propertyme:hostex-sync')->everyFiveMinutes()->withoutOverlapping(10)
    ->when(fn () => config('propertyme.channel_manager_provider') === 'hostex' && config('services.hostex.sync_enabled'));

// Sesuaikan status lama menjadi Confirmed; status pembayaran tidak mengikuti tanggal.
Schedule::command('propertyme:advance-bookings')->hourly();

// Pengingat jadwal besok untuk Staff
Schedule::command('propertyme:notify-tomorrow')->dailyAt(config('propertyme.tomorrow_reminder_at', '17:00'));

// Proses antrean (email notifikasi) — hosting tanpa supervisor
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping(5);

// Bersihkan notifikasi lama & file sementara import
Schedule::command('model:prune')->daily();
