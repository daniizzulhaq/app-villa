<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\Villa;
use App\Services\HostexAllocationService;
use App\Services\HostexSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostexAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Villa $source;

    private Villa $target;

    private array $remote = [];

    private int $remoteCode = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'Asia/Makassar'));
        config(['propertyme.channel_manager_provider' => 'hostex', 'services.hostex.access_token' => 'test-private-token']);
        $this->source = $this->makeVilla(['name' => 'Avora', 'is_active' => false]);
        $this->source->forceFill(['hostex_property_id' => 501])->save();
        $this->target = $this->makeVilla(['name' => 'Sofi 1']);
        $this->target->forceFill(['hostex_property_id' => 502])->save();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertSame('GET', $request->method());
            if ($this->remoteCode !== 200) {
                return Http::response(['error_code' => $this->remoteCode, 'error_msg' => 'private remote response']);
            }
            if (str_starts_with($request->url(), 'https://api.hostex.io/v3/properties?')) {
                return Http::response(['error_code' => 200, 'data' => [
                    'properties' => [['id' => (int) $request['id'], 'title' => 'Remote property', 'channels' => []]], 'total' => 1,
                ]]);
            }
            $this->assertStringStartsWith('https://api.hostex.io/v3/reservations?', $request->url());
            $rows = array_values(array_filter($this->remote[(int) $request['property_id']] ?? [],
                fn ($row) => $row['check_in_date'] >= $request['start_check_in_date'] && $row['check_in_date'] <= $request['end_check_in_date']));

            return Http::response(['error_code' => 200, 'data' => ['reservations' => $rows]]);
        });
    }

    private function row(string $stay = 'stay-1', int $property = 502): array
    {
        return [
            'stay_code' => $stay, 'reservation_code' => 'order-'.$stay, 'property_id' => $property,
            'channel_type' => 'airbnb', 'channel_id' => 'REF-'.$stay,
            'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-04',
            'status' => 'accepted', 'guest_name' => 'Private guest name', 'number_of_guests' => 2,
            'rates' => ['rate' => ['currency' => 'IDR', 'amount' => 700000], 'commission' => ['currency' => 'IDR', 'amount' => 21000]],
        ];
    }

    private function booking(string $stay = 'stay-1', array $overrides = []): Booking
    {
        $booking = Booking::create($overrides + [
            'villa_id' => $this->source->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'source' => 'hostex', 'booking_ref' => 'REF-'.$stay, 'ical_uid' => $stay.'@legacy.test',
            'check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'status' => 'confirmed',
            'notes' => 'Keep local instructions', 'guest_name' => 'Existing private guest',
            'total_price' => 600000, 'price_source' => 'manual',
        ]);
        $booking->forceFill(['hostex_stay_code' => $stay, 'hostex_status' => 'accepted'])->save();

        return $booking->fresh();
    }

    private function args(array $overrides = []): array
    {
        return $overrides + ['--villa' => $this->target->id, '--from' => '2026-11-01', '--to' => '2027-01-01'];
    }

    private function review(bool $apply = false, ?int $sourceId = null): array
    {
        return app(HostexAllocationService::class)->reconcile($this->target, $sourceId, $apply, '2026-11-01', '2027-01-01');
    }

    private function income(Booking $booking, bool $linked = true): Income
    {
        return Income::create([
            'booking_id' => $linked ? $booking->id : null, 'villa_id' => $booking->villa_id,
            'platform_id' => $booking->platform_id, 'booking_ref' => $booking->booking_ref,
            'payment_date' => '2026-11-10', 'gross_amount' => 600000, 'platform_fee' => 18000,
            'net_revenue' => 582000, 'fee_percentage' => 15, 'fee_basis' => $this->source->fee_basis,
            'fee_base_amount' => 582000, 'fee_amount' => 87300,
        ])->fresh();
    }

    public function test_default_preview_finds_cross_villa_stays_without_changing_data_or_exposing_private_fields(): void
    {
        $booking = $this->booking();
        $before = $booking->getRawOriginal();
        $targetBefore = $this->target->fresh()->getRawOriginal();
        $this->remote[502] = [$this->row()];
        $this->artisan('propertyme:hostex-reallocate', ['--from' => '2026-11-01', '--to' => '2027-01-01'])
            ->expectsOutputToContain('1 eligible; 0 conflicts; 0 moved.')
            ->expectsOutputToContain('Preview only.')
            ->doesntExpectOutputToContain('Private guest name')
            ->doesntExpectOutputToContain('Existing private guest')
            ->doesntExpectOutputToContain('Keep local instructions')
            ->doesntExpectOutputToContain('test-private-token')->assertSuccessful();
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertSame($targetBefore, $this->target->fresh()->getRawOriginal());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('incomes', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/reservations?') && $request['property_id'] == 501);
    }

    public function test_apply_preserves_the_booking_then_regular_sync_succeeds_without_duplicates(): void
    {
        $booking = $this->booking();
        $before = $booking->getRawOriginal();
        $this->remote[502] = [$this->row(), $this->row('stay-2')];
        $sync = app(HostexSyncService::class);
        $this->assertStringContainsString('hostex-reallocate', $sync->syncVilla($this->target, '2026-11-01', '2027-01-01')['error']);
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);

        $this->artisan('propertyme:hostex-reallocate', $this->args([
            '--from-villa' => $this->source->id, '--apply' => true,
        ]))->expectsOutputToContain('1 eligible; 0 conflicts; 1 moved.')->assertSuccessful();
        $after = $booking->fresh()->getRawOriginal();
        $this->assertSame($this->target->id, $after['villa_id']);
        foreach ($before as $key => $value) {
            if (! in_array($key, ['villa_id', 'updated_at'], true)) {
                $this->assertSame($value, $after[$key], $key);
            }
        }
        $first = $sync->syncVilla($this->target->fresh(), '2026-11-01', '2027-01-01');
        $this->assertNull($first['error']);
        $this->assertSame(1, $first['created']);
        $this->assertSame('600000.00', $booking->fresh()->total_price);
        $this->assertSame('Keep local instructions', $booking->fresh()->notes);
        $this->assertSame('stay-1@legacy.test', $booking->fresh()->ical_uid);
        $again = $sync->syncVilla($this->target->fresh(), '2026-11-01', '2027-01-01');
        $this->assertNull($again['error']);
        $this->assertSame(0, $again['created']);
        $this->assertSame(2, $again['unchanged']);
        $this->artisan('propertyme:hostex-reallocate', $this->args([
            '--from-villa' => $this->source->id, '--apply' => true,
        ]))->expectsOutputToContain('0 eligible; 0 conflicts; 0 moved.')->assertSuccessful();
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_apply_requires_explicit_distinct_target_and_source(): void
    {
        $booking = $this->booking();
        foreach ([['--apply' => true], $this->args(['--apply' => true]), $this->args([
            '--from-villa' => $this->target->id, '--apply' => true,
        ]), ['--villa' => 'not-an-id']] as $args) {
            $this->artisan('propertyme:hostex-reallocate', $args)->assertFailed();
        }
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);
        Http::assertNothingSent();
    }

    public function test_even_deleted_linked_income_blocks_the_entire_batch(): void
    {
        $first = $this->booking();
        $second = $this->booking('stay-2');
        $income = $this->income($second);
        $income->delete();
        $before = $income->fresh()->getRawOriginal();
        $this->remote[502] = [$this->row(), $this->row('stay-2')];
        $this->artisan('propertyme:hostex-reallocate', $this->args([
            '--from-villa' => $this->source->id, '--apply' => true,
        ]))->expectsOutputToContain('1 eligible; 1 conflicts; 0 moved.')
            ->expectsOutputToContain('income exists')->assertFailed();
        $this->assertSame($this->source->id, $first->fresh()->villa_id);
        $this->assertSame($this->source->id, $second->fresh()->villa_id);
        $this->assertSame($before, $income->fresh()->getRawOriginal());
    }

    public function test_matching_unlinked_income_also_requires_financial_review(): void
    {
        $booking = $this->booking();
        $income = $this->income($booking, false);
        $before = $income->getRawOriginal();
        $this->remote[502] = [$this->row()];
        $result = $this->review(true, $this->source->id);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame(0, $result['moved']);
        $this->assertStringContainsString('income exists', $result['entries'][0]['reason']);
        $this->assertSame($before, $income->fresh()->getRawOriginal());
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);
    }

    public function test_a_stay_still_present_in_the_source_hostex_property_is_not_moved(): void
    {
        $booking = $this->booking();
        $this->remote[501] = [$this->row('stay-1', 501)];
        $this->remote[502] = [$this->row()];
        $result = $this->review(true, $this->source->id);
        $this->assertSame(1, $result['conflicts']);
        $this->assertStringContainsString('still appears', $result['entries'][0]['reason']);
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);
    }

    public function test_deleted_target_reference_collisions_are_not_silently_merged(): void
    {
        $booking = $this->booking();
        $legacy = Booking::create([
            'villa_id' => $this->target->id, 'platform_id' => $booking->platform_id, 'source' => 'ical',
            'booking_ref' => $booking->booking_ref, 'ical_uid' => 'target-copy@legacy.test',
            'check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'status' => 'confirmed',
        ]);
        $legacy->delete();
        $this->remote[502] = [$this->row()];
        $result = $this->review(true, $this->source->id);
        $this->assertSame(1, $result['conflicts']);
        $this->assertStringContainsString('matching reference', $result['entries'][0]['reason']);
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);
        $this->assertTrue($legacy->fresh()->trashed());
    }

    public function test_different_platform_reference_dates_and_deleted_bookings_are_refused(): void
    {
        foreach ([['check_out' => '2026-12-05'], ['booking_ref' => 'DIFFERENT'],
            ['platform_id' => BookingPlatform::where('slug', 'booking-com')->value('id')], []] as $i => $attributes) {
            $stay = 'stay-'.$i;
            $booking = $this->booking($stay, $attributes);
            if ($i === 3) {
                $booking->delete();
            }
            $this->remote[502] = [$this->row($stay)];
            $result = $this->review(true, $this->source->id);
            $this->assertSame(1, $result['conflicts']);
            $this->assertSame(0, $result['moved']);
            $this->assertSame($this->source->id, $booking->fresh()->villa_id);
        }
    }

    public function test_target_ical_without_a_reference_at_the_same_dates_requires_review(): void
    {
        $booking = $this->booking();
        Booking::create([
            'villa_id' => $this->target->id, 'platform_id' => $booking->platform_id, 'source' => 'ical',
            'booking_ref' => null, 'ical_uid' => 'unidentified@legacy.test',
            'check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'status' => 'needs_completion',
        ]);
        $this->remote[502] = [$this->row()];
        $result = $this->review(true, $this->source->id);
        $this->assertSame(1, $result['conflicts']);
        $this->assertStringContainsString('legacy dates', $result['entries'][0]['reason']);
        $this->assertSame(0, $result['moved']);
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_wrong_source_and_active_ical_are_refused_without_changing_the_booking(): void
    {
        $booking = $this->booking();
        $other = $this->makeVilla();
        $other->forceFill(['hostex_property_id' => 503])->save();
        $this->remote[502] = [$this->row()];
        $result = $this->review(true, $other->id);
        $this->assertSame(1, $result['conflicts']);
        $this->assertStringContainsString('--from-villa', $result['entries'][0]['reason']);
        $this->target->channels()->create(['platform_id' => $booking->platform_id, 'ical_url' => 'https://example.test/calendar.ics', 'is_active' => true]);
        $result = $this->review(true, $this->source->id);
        $this->assertStringContainsString('Disable', $result['error']);
        $this->assertSame(0, $result['moved']);
        $this->assertSame($this->source->id, $booking->fresh()->villa_id);
    }

    public function test_api_errors_are_sanitized_and_leave_local_records_unchanged(): void
    {
        $booking = $this->booking();
        $before = $booking->getRawOriginal();
        $this->remoteCode = 401;
        $this->artisan('propertyme:hostex-reallocate', $this->args([
            '--from-villa' => $this->source->id, '--apply' => true,
        ]))->expectsOutputToContain('Hostex rejected the Access Token')
            ->doesntExpectOutputToContain('private remote response')->assertFailed();
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
    }
}
