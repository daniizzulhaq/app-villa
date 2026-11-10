<?php

namespace App\Console\Commands;

use App\Models\Villa;
use App\Services\HostexSyncService;
use Illuminate\Console\Command;

class ReconcileHostexCommand extends Command
{
    protected $signature = 'propertyme:hostex-reconcile
        {--villa= : Required PropertyMe villa ID}
        {--from= : First check-in date}
        {--to= : Last check-in date}
        {--apply : Attach exact, unambiguous existing bookings to Hostex; default is preview only}';

    protected $description = 'Preview or reconcile existing booking references with Hostex without deleting bookings or touching incomes';

    public function handle(HostexSyncService $sync): int
    {
        $id = $this->option('villa');
        if ($id === null || ! ctype_digit((string) $id) || (int) $id < 1) {
            $this->error('Specify one PropertyMe villa with --villa=ID.');

            return self::FAILURE;
        }
        $villa = Villa::active()->whereNotNull('hostex_property_id')->find($id);
        if (! $villa) {
            $this->error('The selected active villa has no Hostex mapping.');

            return self::FAILURE;
        }
        $result = $sync->reconcileVilla($villa, (bool) $this->option('apply'), $this->option('from'), $this->option('to'));
        $this->table(['Booking ID', 'Old source', 'Local dates', 'Hostex dates', 'Linked incomes', 'Result'], array_map(fn ($entry) => [
            $entry['booking_id'], $entry['source'], $entry['check_in'].' / '.$entry['check_out'],
            $entry['hostex_check_in'].' / '.$entry['hostex_check_out'], $entry['incomes'], $entry['reason'] ?? 'Exact match',
        ], $result['entries']));
        $this->info("{$result['eligible']} exact matches; {$result['conflicts']} conflicts; {$result['applied']} reconciled.");
        if ($result['error']) {
            $this->error($result['error']);

            return self::FAILURE;
        }
        if ($result['conflicts']) {
            $this->warn('Manual review is required. Applying this plan will be refused; no changes saved.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->line('Preview only. No changes saved. To apply, repeat this command with the same villa/date options and --apply.');
        } else {
            $this->line('Booking IDs, notes, prices, income links and all income amounts preserved.');
            $this->line("Next: php artisan propertyme:hostex-sync --villa={$villa->id}");
        }

        return self::SUCCESS;
    }
}
