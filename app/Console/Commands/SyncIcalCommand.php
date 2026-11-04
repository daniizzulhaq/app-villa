<?php

namespace App\Console\Commands;

use App\Services\IcalSyncService;
use Illuminate\Console\Command;

class SyncIcalCommand extends Command
{
    protected $signature = 'propertyme:sync-ical {--villa= : Hanya villa tertentu (ID)}';

    protected $description = 'Sinkron reservasi dari link iCal Airbnb / Booking.com';

    public function handle(IcalSyncService $sync): int
    {
        $results = $this->option('villa') ? $sync->syncVilla((int) $this->option('villa')) : $sync->syncAll();

        foreach ($results as $r) {
            $label = $r['channel']->villa->name.' / '.$r['channel']->platform->name;
            $r['error']
                ? $this->error("✗ {$label}: {$r['error']}")
                : $this->info("✓ {$label}: +{$r['created']} baru, {$r['updated']} diperbarui, {$r['cancelled']} batal".($r['warning'] ? " ⚠ {$r['warning']}" : ''));
        }
        $this->line(count($results).' channel diproses.');

        return self::SUCCESS;
    }
}
