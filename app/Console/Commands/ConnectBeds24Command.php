<?php

namespace App\Console\Commands;

use App\Exceptions\Beds24Exception;
use App\Services\Beds24Client;
use Illuminate\Console\Command;
use Throwable;

class ConnectBeds24Command extends Command
{
    protected $signature = 'propertyme:beds24-connect {--check : Only check the existing connection and list property/room IDs}';

    protected $description = 'Exchange a Beds24 invite code securely, or check read access to properties';

    public function handle(Beds24Client $client): int
    {
        try {
            if (! $this->option('check')) {
                if (filled(config('services.beds24.token'))) {
                    $this->error('BEDS24_TOKEN is set. Clear it before using an invite code, or use --check to test that long-life token.');

                    return self::FAILURE;
                }
                $code = config('services.beds24.invite_code');
                if (blank($code)) {
                    $code = $this->secret('Paste the NEW Beds24 invite code (input hidden)');
                }
                $client->connect((string) $code);
                unset($code);
                $this->info('Credentials saved encrypted in private storage.');
            }
            $properties = $client->properties(true);
            $rows = [];
            foreach ($properties as $property) {
                $rooms = $property['roomTypes'] ?? [];
                if (! $rooms) {
                    $rows[] = [$property['id'], $property['name'] ?? '', '-', '-'];
                }
                foreach ($rooms as $room) {
                    $rows[] = [$property['id'], $property['name'] ?? '', $room['id'] ?? '', $room['name'] ?? ''];
                }
            }
            $this->table(['Property ID', 'Property name', 'Room ID', 'Room name'], $rows);
            $this->info('Connection verified: '.count($properties).' properties accessible.');
            $this->line('No bookings imported. Match these IDs to PropertyMe villas before enabling reservation sync.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof Beds24Exception ? $exception->getMessage() : 'Beds24 connection check failed. Check configuration and private storage permissions.');

            return self::FAILURE;
        }
    }
}
