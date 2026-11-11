<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class AdvanceBookingStatusCommand extends Command
{
    protected $signature = 'propertyme:advance-bookings';

    protected $description = 'Sesuaikan status Checked-in / Checked-out lama menjadi Confirmed';

    public function handle(BookingService $bookings): int
    {
        $this->info($bookings->advanceStatuses().' reservasi diperbarui.');

        return self::SUCCESS;
    }
}
