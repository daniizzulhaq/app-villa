<?php

namespace App\Console\Commands;

use App\Exceptions\HostexException;
use App\Models\Villa;
use App\Services\HostexClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MapHostexVillaCommand extends Command
{
    protected $signature = 'propertyme:hostex-map {villa : PropertyMe villa ID} {property : Hostex property ID}';

    protected $description = 'Validate a Hostex property and save its mapping to a local villa';

    public function handle(HostexClient $client): int
    {
        try {
            foreach (['villa', 'property'] as $argument) {
                if (! ctype_digit((string) $this->argument($argument)) || (int) $this->argument($argument) < 1) {
                    throw new HostexException('Villa and Hostex property must be positive numeric IDs.');
                }
            }
            $villa = Villa::find($this->argument('villa'));
            if (! $villa) {
                throw new HostexException('PropertyMe villa was not found.');
            }
            $propertyId = (int) $this->argument('property');
            $property = collect($client->properties($propertyId))->firstWhere('id', $propertyId);
            if (! $property) {
                throw new HostexException('The selected Hostex property was not found.');
            }
            DB::transaction(function () use ($villa, $propertyId) {
                $villa = Villa::lockForUpdate()->findOrFail($villa->id);
                if ($villa->hostex_property_id !== null && (int) $villa->hostex_property_id !== $propertyId) {
                    throw new HostexException('This villa already has a different Hostex mapping. No mapping changed.');
                }
                if (Villa::withTrashed()->where('hostex_property_id', $propertyId)->whereKeyNot($villa->id)->exists()) {
                    throw new HostexException('This Hostex property is already mapped to another villa.');
                }
                $villa->forceFill(['hostex_property_id' => $propertyId])->save();
            });
            $this->info("Local villa #{$villa->id} mapped to Hostex property {$propertyId}.");
            $this->line("Mapping saved. Run php artisan propertyme:hostex-sync --villa={$villa->id} to import reservations.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof HostexException ? $exception->getMessage() : 'Could not save the Hostex mapping. Check configuration, database and connection.');

            return self::FAILURE;
        }
    }
}
