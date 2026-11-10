<?php

namespace App\Console\Commands;

use App\Exceptions\HostexException;
use App\Services\HostexClient;
use Illuminate\Console\Command;
use Throwable;

class CheckHostexCommand extends Command
{
    protected $signature = 'propertyme:hostex-check
        {--property= : Hostex property ID to check, including reservation read access}
        {--from= : First check-in date (YYYY-MM-DD)}
        {--to= : Last check-in date (YYYY-MM-DD)}';

    protected $description = 'Check read-only Hostex API access and list properties without importing data';

    public function handle(HostexClient $client): int
    {
        try {
            $propertyId = $this->option('property');
            if ($propertyId !== null && (! ctype_digit((string) $propertyId) || (int) $propertyId < 1)) {
                throw new HostexException('Hostex property ID must be a positive number.');
            }
            $properties = $client->properties($propertyId === null ? null : (int) $propertyId);
            $rows = [];
            foreach ($properties as $property) {
                $channels = $property['channels'] ?? [];
                foreach ($channels ?: [[]] as $channel) {
                    $rows[] = [$property['id'], $property['title'] ?? '', $channel['channel_type'] ?? '-', $channel['listing_id'] ?? '-'];
                }
            }
            $this->table(['Hostex property ID', 'Property name', 'Channel', 'Listing ID'], $rows);
            $this->info('Connection verified: '.count($properties).' properties accessible.');
            if ($propertyId !== null) {
                if (count($properties) !== 1) {
                    throw new HostexException('The selected Hostex property was not found.');
                }
                $from = (string) ($this->option('from') ?? today()->subYear()->toDateString());
                $to = (string) ($this->option('to') ?? today()->addYear()->toDateString());
                $reservations = $client->reservations((int) $propertyId, $from, $to);
                $this->info('Reservation read verified: '.count($reservations)." stays with check-in {$from} to {$to}.");
            }
            $this->line('Read-only check. No bookings imported or OTA settings changed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof HostexException ? $exception->getMessage() : 'Hostex check failed. Check configuration and try again.');

            return self::FAILURE;
        }
    }
}
