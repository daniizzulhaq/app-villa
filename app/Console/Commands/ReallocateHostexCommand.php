<?php

namespace App\Console\Commands;

use App\Models\Villa;
use App\Services\HostexAllocationService;
use Illuminate\Console\Command;

class ReallocateHostexCommand extends Command
{
    protected $signature = 'propertyme:hostex-reallocate
        {--villa= : Target PropertyMe villa ID; omitted for a preview of all mapped active villas}
        {--from-villa= : Expected current PropertyMe villa ID; required when applying}
        {--from= : First check-in date}
        {--to= : Last check-in date}
        {--apply : Move verified Hostex bookings without income; default is preview only}';

    protected $description = 'Preview or correct Hostex booking allocations after splitting properties; never move income records';

    public function handle(HostexAllocationService $allocations): int
    {
        $targetId = $this->option('villa');
        $sourceId = $this->option('from-villa');
        foreach ([$targetId, $sourceId] as $id) {
            if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
                $this->error('Villa IDs must be positive numbers.');

                return self::FAILURE;
            }
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ($targetId === null || $sourceId === null || (int) $targetId === (int) $sourceId)) {
            $this->error('Specify different --villa=TARGET_ID and --from-villa=SOURCE_ID when applying.');

            return self::FAILURE;
        }
        $villas = Villa::active()->whereNotNull('hostex_property_id')->when($targetId !== null, fn ($q) => $q->whereKey($targetId))->orderBy('id')->get();
        if ($villas->isEmpty()) {
            $this->error('No matching active villa with a Hostex mapping was found.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($villas as $villa) {
            $this->info("Target villa #{$villa->id}; Hostex property {$villa->hostex_property_id}.");
            $result = $allocations->reconcile($villa, $sourceId === null ? null : (int) $sourceId, $apply, $this->option('from'), $this->option('to'));
            $this->table(['Booking ID', 'Current villa', 'Target villa', 'Dates', 'Income records', 'Result'], array_map(fn ($entry) => [
                $entry['booking_id'], $entry['from_villa'], $entry['to_villa'],
                $entry['check_in'].' / '.$entry['check_out'], $entry['incomes'], $entry['reason'] ?? 'Verified move',
            ], $result['entries']));
            $this->info("{$result['eligible']} eligible; {$result['conflicts']} conflicts; {$result['moved']} moved.");
            if ($result['error']) {
                $this->error($result['error']);
            }
            $failed = $failed || $result['error'] !== null || $result['conflicts'] > 0;
        }
        if (! $apply) {
            $this->line('Preview only. No booking, villa, sync status or income records changed.');
            $this->line('To apply a reviewed plan with zero conflicts, specify --villa=TARGET_ID --from-villa=SOURCE_ID --apply.');
        } else {
            $this->line('Booking IDs, notes, prices and existing fields retained. No income records or OTA settings changed.');
            if (! $failed) {
                $this->line("Next: php artisan propertyme:hostex-sync --villa={$targetId}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
