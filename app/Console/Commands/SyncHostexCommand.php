<?php

namespace App\Console\Commands;

use App\Models\Villa;
use App\Services\HostexSyncService;
use Illuminate\Console\Command;

class SyncHostexCommand extends Command
{
    protected $signature = 'propertyme:hostex-sync {--villa= : PropertyMe villa ID} {--from= : First check-in date} {--to= : Last check-in date}';

    protected $description = 'Pull Hostex reservations into mapped PropertyMe villas without changing the OTA';

    public function handle(HostexSyncService $sync): int
    {
        $id = $this->option('villa');
        if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) {
            $this->error('A valid PropertyMe villa ID is required.');

            return self::FAILURE;
        }
        $villas = Villa::active()->whereNotNull('hostex_property_id')->when($id, fn ($q) => $q->whereKey($id))->get();
        if ($id !== null && $villas->isEmpty()) {
            $this->error('The selected active villa has no Hostex mapping.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($villas as $villa) {
            $result = $sync->syncVilla($villa, $this->option('from'), $this->option('to'));
            if ($result['error']) {
                $this->error("Villa #{$villa->id}: ".$result['error']);
                if ($this->output->isVerbose()) {
                    $this->line($result['diagnostic'] ?? '');
                }
                $failed = true;
            } else {
                $this->info("Villa #{$villa->id}: {$result['created']} created, {$result['updated']} updated, {$result['unchanged']} unchanged, {$result['skipped']} skipped.");
                if ($result['foreign_currency']) {
                    $this->warn($result['foreign_currency'].' stays have non-IDR prices; original currency is retained and no Rupiah conversion was assumed.');
                }
            }
        }
        $this->line('Hostex pull complete. No OTA settings or income ledger entries changed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
