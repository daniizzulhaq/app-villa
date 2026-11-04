<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class AdvanceBookingStatusCommand extends Command
{
    protected $signature = 'propertyme:advance-bookings';

    protected $description = 'Ubah status Confirmed → Checked-in → Checked-out sesuai tanggal';

    public function handle(BookingService $bookings): int
    {
        $this->info($bookings->advanceStatuses().' reservasi diperbarui.');

        return self::SUCCESS;
    }
}
