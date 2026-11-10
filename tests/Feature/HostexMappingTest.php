<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostexMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['propertyme.channel_manager_provider' => 'hostex', 'services.hostex.access_token' => 'test-token']);
    }

    private function property(int $id = 501): void
    {
        Http::fake(['api.hostex.io/v3/properties*' => Http::response([
            'error_code' => 200, 'data' => ['properties' => [['id' => $id, 'title' => 'Avora', 'channels' => []]], 'total' => 1],
        ])]);
    }

    public function test_mapping_is_repeatable_and_does_not_reuse_or_modify_beds24_ids(): void
    {
        $villa = $this->makeVilla();
        $villa->forceFill(['beds24_property_id' => 367374, 'beds24_room_id' => 756263])->save();
        $other = $this->makeVilla();
        $this->property();
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('propertyme:hostex-map', ['villa' => $villa->id, 'property' => 501])
                ->expectsOutputToContain('mapped to Hostex property 501.')->assertSuccessful();
        }
        $this->assertDatabaseHas('villas', ['id' => $villa->id, 'hostex_property_id' => 501, 'beds24_property_id' => 367374, 'beds24_room_id' => 756263]);
        $this->assertNull($other->fresh()->hostex_property_id);
        $this->assertDatabaseCount('bookings', 0);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r['id'] == 501);
    }

    public function test_mapping_cannot_be_reassigned_or_shared_with_a_deleted_villa(): void
    {
        $mapped = $this->makeVilla();
        $mapped->forceFill(['hostex_property_id' => 501])->save();
        $other = $this->makeVilla();
        $other->forceFill(['hostex_property_id' => 502])->save();
        $this->property();
        $this->artisan('propertyme:hostex-map', ['villa' => $other->id, 'property' => 501])
            ->expectsOutputToContain('already has a different Hostex mapping')->assertFailed();
        $mapped->delete();
        $third = $this->makeVilla();
        $this->artisan('propertyme:hostex-map', ['villa' => $third->id, 'property' => 501])
            ->expectsOutputToContain('already mapped to another villa')->assertFailed();
        $this->assertNull($third->fresh()->hostex_property_id);
        $this->assertSame(502, (int) $other->fresh()->hostex_property_id);
    }

    public function test_invalid_remote_property_does_not_create_a_mapping(): void
    {
        $villa = $this->makeVilla();
        $this->property(999);
        $this->artisan('propertyme:hostex-map', ['villa' => $villa->id, 'property' => 501])
            ->expectsOutputToContain('different property than requested')->assertFailed();
        $this->assertNull($villa->fresh()->hostex_property_id);
    }
}
