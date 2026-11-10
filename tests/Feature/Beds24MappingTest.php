<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Beds24MappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['propertyme.channel_manager_provider' => 'beds24']);
        Http::preventStrayRequests();
        config(['services.beds24.token' => 'test-token']);
    }

    private function remoteRoom(int $qty = 1): void
    {
        Http::fake(['api.beds24.com/v2/properties*' => Http::response(['data' => [
            ['id' => 101, 'roomTypes' => [['id' => 201, 'qty' => $qty]]],
        ]])]);
    }

    public function test_mapping_is_validated_and_repeatable_without_changing_other_villas(): void
    {
        $villa = $this->makeVilla();
        $other = $this->makeVilla();
        $this->remoteRoom();
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('propertyme:beds24-map', ['villa' => $villa->id, 'property' => 101, 'room' => 201])
                ->expectsOutputToContain('mapped to Beds24 property 101, room 201.')->assertSuccessful();
        }
        $this->assertDatabaseHas('villas', ['id' => $villa->id, 'beds24_property_id' => 101, 'beds24_room_id' => 201]);
        $this->assertNull($other->fresh()->beds24_property_id);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r['includeAllRooms'] === 'true');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_room_cannot_be_assigned_to_two_villas_even_if_one_was_deleted(): void
    {
        $mapped = $this->makeVilla();
        $mapped->forceFill(['beds24_property_id' => 101, 'beds24_room_id' => 201])->save();
        $mapped->delete();
        $villa = $this->makeVilla();
        $this->remoteRoom();
        $this->artisan('propertyme:beds24-map', ['villa' => $villa->id, 'property' => 101, 'room' => 201])
            ->expectsOutputToContain('already mapped to another villa')->assertFailed();
        $this->assertNull($villa->fresh()->beds24_property_id);
    }

    public function test_existing_mapping_is_not_silently_replaced(): void
    {
        $villa = $this->makeVilla();
        $villa->forceFill(['beds24_property_id' => 102, 'beds24_room_id' => 202])->save();
        $this->remoteRoom();
        $this->artisan('propertyme:beds24-map', ['villa' => $villa->id, 'property' => 101, 'room' => 201])
            ->expectsOutputToContain('already has a different Beds24 mapping')->assertFailed();
        $this->assertDatabaseHas('villas', ['id' => $villa->id, 'beds24_property_id' => 102, 'beds24_room_id' => 202]);
    }

    public function test_wrong_property_and_multi_unit_rooms_are_not_mapped(): void
    {
        $villa = $this->makeVilla();
        $this->remoteRoom(2);
        $this->artisan('propertyme:beds24-map', ['villa' => $villa->id, 'property' => 999, 'room' => 201])
            ->expectsOutputToContain('room was not found')->assertFailed();
        $this->artisan('propertyme:beds24-map', ['villa' => $villa->id, 'property' => 101, 'room' => 201])
            ->expectsOutputToContain('exactly one rentable unit')->assertFailed();
        $this->assertNull($villa->fresh()->beds24_property_id);
    }
}
