<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jalankan dari Cron Job cPanel tiap menit:
|   * * * * * cd /home/USER/propertyme && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
*/

// Sinkron iCal Airbnb & Booking.com tiap 30 menit
Schedule::command('propertyme:sync-ical')->everyThirtyMinutes()->withoutOverlapping(25);

// Status booking mengikuti tanggal (Checked-in / Checked-out)
Schedule::command('propertyme:advance-bookings')->hourly();

// Pengingat jadwal besok untuk Staff
Schedule::command('propertyme:notify-tomorrow')->dailyAt(config('propertyme.tomorrow_reminder_at', '17:00'));

// Proses antrean (email notifikasi) — hosting tanpa supervisor
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping(5);

// Bersihkan notifikasi lama & file sementara import
Schedule::command('model:prune')->daily();
