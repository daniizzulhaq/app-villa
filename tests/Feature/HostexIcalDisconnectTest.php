<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\Region;
use App\Models\Villa;
use App\Services\IcalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostexIcalDisconnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        config(['propertyme.channel_manager_provider' => 'hostex']);
        Http::preventStrayRequests();
    }

    private function mappedVilla(array $attributes = []): Villa
    {
        $villa = $this->makeVilla($attributes);
        $villa->forceFill(['hostex_property_id' => 501])->save();

        return $villa;
    }

    private function feed(Villa $villa, string $platform = 'airbnb', bool $active = true)
    {
        return $villa->channels()->create([
            'platform_id' => BookingPlatform::where('slug', $platform)->value('id'),
            'ical_url' => 'https://calendar.example.test/'.$villa->id.'/'.$platform.'.ics?token=private-feed',
            'is_active' => $active,
            'last_synced_at' => now()->subDay(), 'last_success_at' => now()->subDay(),
        ]);
    }

    private function calendar(): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:one-stay@example.test\r\nDTSTART;VALUE=DATE:20261201\r\nDTEND;VALUE=DATE:20261204\r\nSUMMARY:Reserved\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }

    public function test_disconnect_is_per_villa_repeatable_and_preserves_feeds_bookings_and_income(): void
    {
        $master = $this->makeUser(UserRole::Master, ['locale' => 'id']);
        $villa = $this->mappedVilla();
        $feeds = collect(['airbnb', 'booking-com', 'lainnya'])->map(fn ($platform) => $this->feed($villa, $platform));
        $other = $this->feed($this->makeVilla());
        $booking = Booking::create([
            'villa_id' => $villa->id, 'platform_id' => $feeds->first()->platform_id,
            'source' => 'ical', 'ical_uid' => 'old-stay@example.test', 'booking_ref' => 'OLDREF',
            'check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'status' => 'confirmed',
            'guest_name' => 'Existing Guest', 'notes' => 'Keep local instructions',
            'total_price' => 2500000, 'price_source' => 'manual',
        ]);
        $income = Income::create([
            'booking_id' => $booking->id, 'villa_id' => $villa->id, 'platform_id' => $booking->platform_id,
            'booking_ref' => $booking->booking_ref, 'payment_date' => '2026-11-10', 'gross_amount' => 2500000,
            'platform_fee' => 100000, 'net_revenue' => 2400000, 'fee_percentage' => 15,
            'fee_basis' => $villa->fee_basis, 'fee_base_amount' => 2400000, 'fee_amount' => 360000,
            'created_by' => $master->id,
        ]);
        $bookingBefore = $booking->fresh()->getRawOriginal();
        $incomeBefore = $income->fresh()->getRawOriginal();
        $villaBefore = $villa->fresh()->getRawOriginal();

        $this->actingAs($master)->get(route('villas.show', $villa))->assertOk()
            ->assertSee(__('Disable iCal'))->assertSee(route('villas.disable-ical', $villa), false);
        $this->get(route('sync.index'))->assertOk()->assertSee(route('villas.disable-ical', $villa), false);

        for ($i = 0; $i < 2; $i++) {
            $this->patch(route('villas.disable-ical', $villa))->assertRedirect(route('villas.show', $villa))
                ->assertSessionHas('success');
        }

        foreach ($feeds as $feed) {
            $saved = $feed->fresh();
            $this->assertFalse($saved->is_active);
            $this->assertSame($feed->ical_url, $saved->ical_url);
            $this->assertEquals($feed->last_success_at, $saved->last_success_at);
        }
        $this->assertTrue($other->fresh()->is_active);
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame($incomeBefore, $income->fresh()->getRawOriginal());
        $this->assertSame($villaBefore, $villa->fresh()->getRawOriginal());
        $this->assertDatabaseCount('villa_channels', 4);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('incomes', 1);
        $this->get(route('villas.show', $villa))->assertOk()
            ->assertSee(__('iCal is disabled. Bookings are imported through Hostex.'))
            ->assertDontSee(route('villas.disable-ical', $villa), false);
        $this->get(route('sync.index'))->assertOk()->assertDontSee(route('villas.disable-ical', $villa), false);
        Http::assertNothingSent();
    }

    public function test_only_master_or_the_villas_regional_manager_can_disconnect(): void
    {
        $region = Region::create(['name' => 'Bali']);
        $manager = $this->makeUser(UserRole::Manager);
        $region->managers()->attach($manager);
        $villa = $this->mappedVilla(['region_id' => $region->id]);
        $feed = $this->feed($villa);
        $outsideManager = $this->makeUser(UserRole::Manager);
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $villa->update(['owner_id' => $owner->id]);
        $villa->staff()->attach($staff);

        $this->patch(route('villas.disable-ical', $villa))->assertRedirect(route('login'));
        foreach ([$outsideManager, $owner, $staff] as $user) {
            $this->actingAs($user)->patch(route('villas.disable-ical', $villa))->assertForbidden();
            $this->assertTrue($feed->fresh()->is_active);
        }

        $this->actingAs($manager)->patch(route('villas.disable-ical', $villa))
            ->assertRedirect(route('villas.show', $villa))->assertSessionHas('success');
        $this->assertFalse($feed->fresh()->is_active);
        Http::assertNothingSent();
    }

    public function test_unmapped_villas_and_inactive_hostex_provider_cannot_use_the_action(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $feed = $this->feed($villa);
        $this->actingAs($master)->get(route('villas.show', $villa))->assertOk()
            ->assertDontSee(route('villas.disable-ical', $villa), false);
        $this->patch(route('villas.disable-ical', $villa))->assertUnprocessable();
        $this->assertTrue($feed->fresh()->is_active);

        $villa->forceFill(['hostex_property_id' => 501])->save();
        config(['propertyme.channel_manager_provider' => 'beds24']);
        $this->patch(route('villas.disable-ical', $villa))->assertUnprocessable();
        $this->assertTrue($feed->fresh()->is_active);
        Http::assertNothingSent();
    }

    public function test_disabled_feeds_are_skipped_while_other_villas_can_still_import_ical(): void
    {
        $villa = $this->makeVilla();
        $staleFeed = $this->feed($villa);
        $staleFeed->newQuery()->whereKey($staleFeed->id)->update(['is_active' => false]);
        $sync = app(IcalSyncService::class);

        $this->assertSame([], $sync->syncVilla($villa->id));
        $result = $sync->syncChannel($staleFeed);
        $this->assertNull($result['error']);
        $this->assertNotNull($result['warning']);
        $this->assertSame(0, $result['created']);
        $this->assertEquals($staleFeed->last_success_at, $staleFeed->fresh()->last_success_at);
        Http::assertNothingSent();

        $activeFeed = $this->feed($this->makeVilla());
        Http::fake([$activeFeed->ical_url => Http::response($this->calendar())]);
        $results = $sync->syncAll();
        $this->assertCount(1, $results);
        $this->assertNull($results[0]['error']);
        $this->assertSame(1, $results[0]['created']);
        $this->assertDatabaseHas('bookings', ['villa_id' => $activeFeed->villa_id, 'source' => 'ical']);
        $this->assertDatabaseMissing('bookings', ['villa_id' => $villa->id]);
        Http::assertSentCount(1);
    }

    public function test_disabling_a_feed_during_download_prevents_import_and_keeps_sync_history(): void
    {
        $villa = $this->makeVilla();
        $feed = $this->feed($villa);
        $before = $feed->fresh()->getRawOriginal();
        $booking = Booking::create([
            'villa_id' => $villa->id, 'platform_id' => $feed->platform_id,
            'source' => 'ical', 'ical_uid' => 'keep-stay@example.test',
            'check_in' => '2026-12-06', 'check_out' => '2026-12-09', 'status' => 'confirmed',
        ]);
        $bookingBefore = $booking->fresh()->getRawOriginal();
        Http::fake([$feed->ical_url => function () use ($villa, $feed) {
            $villa->forceFill(['hostex_property_id' => 501])->save();
            $feed->newQuery()->whereKey($feed->id)->update(['is_active' => false]);

            return Http::response($this->calendar());
        }]);

        $result = app(IcalSyncService::class)->syncChannel($feed);
        $this->assertNull($result['error']);
        $this->assertNotNull($result['warning']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['cancelled']);
        $saved = $feed->fresh();
        $this->assertFalse($saved->is_active);
        foreach (['ical_url', 'last_synced_at', 'last_success_at', 'last_error'] as $key) {
            $this->assertSame($before[$key], $saved->getRawOriginal($key));
        }
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        Http::assertSentCount(1);
    }
}
