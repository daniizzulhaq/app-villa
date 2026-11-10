<?php

namespace App\Console\Commands;

use App\Exceptions\Beds24Exception;
use App\Models\Villa;
use App\Services\Beds24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MapBeds24VillaCommand extends Command
{
    protected $signature = 'propertyme:beds24-map {villa : PropertyMe villa ID} {property : Beds24 property ID} {room : Beds24 room ID}';

    protected $description = 'Validate a Beds24 property/room and save its local villa mapping without changing the OTA';

    public function handle(Beds24Client $client): int
    {
        try {
            foreach (['villa', 'property', 'room'] as $argument) {
                if (! ctype_digit((string) $this->argument($argument)) || (int) $this->argument($argument) < 1) {
                    throw new Beds24Exception('Villa, property and room must be positive numeric IDs.');
                }
            }
            $villa = Villa::find($this->argument('villa'));
            if (! $villa) {
                throw new Beds24Exception('PropertyMe villa was not found.');
            }
            $propertyId = (int) $this->argument('property');
            $roomId = (int) $this->argument('room');
            $property = collect($client->properties(true))->firstWhere('id', $propertyId);
            $room = collect($property['roomTypes'] ?? [])->firstWhere('id', $roomId);
            if (! $property || ! $room) {
                throw new Beds24Exception('The selected room was not found in this accessible Beds24 property.');
            }
            if ((int) ($room['qty'] ?? 0) !== 1) {
                throw new Beds24Exception('Only a room type with exactly one rentable unit can map to one villa.');
            }
            DB::transaction(function () use ($villa, $propertyId, $roomId) {
                $villa = Villa::lockForUpdate()->findOrFail($villa->id);
                if ($villa->beds24_property_id !== null && (
                    (int) $villa->beds24_property_id !== $propertyId || (int) $villa->beds24_room_id !== $roomId
                )) {
                    throw new Beds24Exception('This villa already has a different Beds24 mapping. No mapping changed.');
                }
                if (Villa::withTrashed()->where('beds24_property_id', $propertyId)->where('beds24_room_id', $roomId)->whereKeyNot($villa->id)->exists()) {
                    throw new Beds24Exception('This Beds24 room is already mapped to another villa.');
                }
                $villa->forceFill(['beds24_property_id' => $propertyId, 'beds24_room_id' => $roomId])->save();
            });
            $this->info("Local villa #{$villa->id} mapped to Beds24 property {$propertyId}, room {$roomId}.");
            $this->line('No reservations, rates or availability changed. Booking import is not enabled yet.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof Beds24Exception ? $exception->getMessage() : 'Could not save the Beds24 mapping. Check the database and connection.');

            return self::FAILURE;
        }
    }
}
