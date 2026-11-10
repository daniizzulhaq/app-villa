<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\Villa;
use App\Services\HostexSyncService;
use App\Services\IcalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostexSyncTest extends TestCase
{
    use RefreshDatabase;

    private array $remoteRows = [];

    private int $remoteCode = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'Asia/Makassar'));
        config(['propertyme.channel_manager_provider' => 'hostex', 'services.hostex.access_token' => 'test-hostex-token']);
        Http::preventStrayRequests();
        Http::fake(['api.hostex.io/v3/reservations*' => fn () => Http::response([
            'error_code' => $this->remoteCode, 'error_msg' => 'sensitive error', 'data' => ['reservations' => $this->remoteRows],
        ])]);
    }

    private function row(array $override = []): array
    {
        return $override + [
            'stay_code' => 'stay-1', 'reservation_code' => 'order-1', 'property_id' => 501,
            'channel_type' => 'airbnb', 'channel_id' => 'HMAVORA', 'listing_id' => '1273618741658905815',
            'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-04',
            'status' => 'accepted', 'guest_name' => 'Test Guest', 'guest_phone' => '+62 812 9999 1234',
            'number_of_guests' => 2, 'remarks' => 'Private note in Hostex',
            'rates' => ['rate' => ['currency' => 'IDR', 'amount' => 3000000],
                'commission' => ['currency' => 'IDR', 'amount' => 90000], 'total_rate' => ['currency' => 'IDR', 'amount' => 99000000]],
            'check_in_details' => ['arrival_at' => ['hour' => 14, 'minute' => 30], 'lock_code' => 'not-for-import'],
        ];
    }

    private function remote(array $rows): void
    {
        $this->remoteRows = $rows;
        $this->remoteCode = 200;
    }

    private function villa(): Villa
    {
        $villa = $this->makeVilla();
        $villa->forceFill(['hostex_property_id' => 501])->save();

        return $villa;
    }

    private function sync(Villa $villa): array
    {
        return app(HostexSyncService::class)->syncVilla($villa, '2026-11-01', '2027-01-01');
    }

    private function legacyBooking(Villa $villa, array $override = []): Booking
    {
        return Booking::create($override + [
            'villa_id' => $villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'source' => 'ical', 'ical_uid' => 'legacy-'.uniqid().'@example.test', 'booking_ref' => 'HMAVORA',
            'guest_name' => 'Existing Local Guest', 'notes' => 'Keep preparation notes',
            'check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'status' => 'confirmed',
            'total_price' => 2500000, 'price_source' => null,
        ]);
    }

    public function test_reconcile_preview_is_read_only_and_apply_preserves_booking_id_income_links_and_prices(): void
    {
        $villa = $this->villa();
        $booking = $this->legacyBooking($villa);
        $income = Income::create([
            'booking_id' => $booking->id, 'villa_id' => $villa->id, 'platform_id' => $booking->platform_id,
            'booking_ref' => $booking->booking_ref, 'payment_date' => '2026-11-10', 'gross_amount' => 2500000,
            'platform_fee' => 100000, 'net_revenue' => 2400000, 'fee_percentage' => 15,
            'fee_basis' => $villa->fee_basis, 'fee_base_amount' => 2400000, 'fee_amount' => 360000,
        ]);
        $incomeBefore = $income->fresh()->getRawOriginal();
        $bookingBefore = $booking->fresh()->getRawOriginal();
        $this->remote([$this->row()]);
        $args = ['--villa' => $villa->id, '--from' => '2026-11-01', '--to' => '2027-01-01'];
        $this->artisan('propertyme:hostex-reconcile', $args)
            ->expectsOutputToContain('1 exact matches; 0 conflicts; 0 reconciled.')
            ->doesntExpectOutputToContain('Existing Local Guest')->doesntExpectOutputToContain('test-hostex-token')->assertSuccessful();
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertNull($villa->fresh()->hostex_last_synced_at);
        $this->artisan('propertyme:hostex-reconcile', $args + ['--apply' => true])
            ->expectsOutputToContain('1 exact matches; 0 conflicts; 1 reconciled.')->assertSuccessful();
        $booking->refresh();
        $this->assertSame('hostex', $booking->source);
        $this->assertSame('stay-1', $booking->hostex_stay_code);
        $this->assertSame('manual', $booking->price_source);
        $this->assertSame('2500000.00', $booking->total_price);
        $this->assertSame('Keep preparation notes', $booking->notes);
        $this->assertNotNull($booking->ical_uid);
        $this->assertSame(0, $this->sync($villa)['created']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('incomes', 1);
        $this->assertSame($incomeBefore, $income->fresh()->getRawOriginal());
        $this->assertSame('3000000.00', $booking->fresh()->hostex_rate_amount);
        $this->assertSame('2500000.00', $booking->fresh()->total_price);
        $this->artisan('propertyme:hostex-reconcile', $args + ['--apply' => true])
            ->expectsOutputToContain('0 exact matches; 0 conflicts; 0 reconciled.')->assertSuccessful();
    }

    public function test_reconciliation_refuses_mismatched_dates_and_applies_nothing_even_with_another_exact_match(): void
    {
        $villa = $this->villa();
        $exact = $this->legacyBooking($villa, ['booking_ref' => 'SECOND']);
        $mismatch = $this->legacyBooking($villa, ['check_out' => '2026-12-05']);
        $this->remote([$this->row(['stay_code' => 'stay-2', 'channel_id' => 'SECOND']), $this->row()]);
        $this->artisan('propertyme:hostex-reconcile', ['--villa' => $villa->id, '--apply' => true])
            ->expectsOutputToContain('1 exact matches; 1 conflicts; 0 reconciled.')
            ->expectsOutputToContain('No changes saved.')->assertFailed();
        $this->assertSame('ical', $exact->fresh()->source);
        $this->assertSame('ical', $mismatch->fresh()->source);
        $this->assertNull($exact->fresh()->hostex_stay_code);
        $this->assertNull($mismatch->fresh()->hostex_stay_code);
    }

    public function test_multiple_legacy_candidates_or_multiple_stays_for_one_booking_are_not_auto_merged(): void
    {
        $villa = $this->villa();
        $first = $this->legacyBooking($villa);
        $second = $this->legacyBooking($villa, ['source' => 'manual']);
        $this->remote([$this->row()]);
        $args = ['--villa' => $villa->id, '--apply' => true];
        $this->artisan('propertyme:hostex-reconcile', $args)->expectsOutputToContain('Multiple existing bookings')->assertFailed();
        $this->assertSame('ical', $first->fresh()->source);
        $this->assertSame('manual', $second->fresh()->source);
        $second->delete();
        $this->remote([$this->row(), $this->row(['stay_code' => 'stay-2'])]);
        $this->artisan('propertyme:hostex-reconcile', $args)->expectsOutputToContain('Multiple Hostex stays')->assertFailed();
        $this->assertNull($first->fresh()->hostex_stay_code);
        $this->assertTrue($second->fresh()->trashed());
    }

    public function test_reconciliation_does_not_touch_matching_references_in_other_villas_or_platforms(): void
    {
        $villa = $this->villa();
        $booking = $this->legacyBooking($villa, ['source' => 'import', 'price_source' => 'import']);
        $otherVilla = $this->legacyBooking($this->makeVilla());
        $otherPlatform = $this->legacyBooking($villa, ['platform_id' => BookingPlatform::where('slug', 'booking-com')->value('id')]);
        $this->remote([$this->row()]);
        $this->artisan('propertyme:hostex-reconcile', ['--villa' => $villa->id, '--apply' => true])
            ->expectsOutputToContain('1 exact matches; 0 conflicts; 1 reconciled.')->assertSuccessful();
        $this->assertSame('hostex', $booking->fresh()->source);
        $this->assertSame('import', $booking->fresh()->price_source);
        $this->assertSame('ical', $otherVilla->fresh()->source);
        $this->assertSame('ical', $otherPlatform->fresh()->source);
    }

    public function test_reconciliation_refuses_an_existing_hostex_stay_instead_of_merging_two_booking_ids(): void
    {
        $villa = $this->villa();
        $this->remote([$this->row()]);
        $this->sync($villa);
        $legacy = $this->legacyBooking($villa);
        $this->artisan('propertyme:hostex-reconcile', ['--villa' => $villa->id, '--apply' => true])
            ->expectsOutputToContain('Hostex identifier is already assigned')->assertFailed();
        $this->assertSame('ical', $legacy->fresh()->source);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_pull_imports_per_stay_values_once_and_never_creates_a_payout(): void
    {
        $villa = $this->villa();
        $this->remote([$this->row(), $this->row(['stay_code' => 'stay-2', 'check_in_date' => '2026-12-10', 'check_out_date' => '2026-12-12'])]);
        $this->artisan('propertyme:hostex-sync', ['--villa' => $villa->id, '--from' => '2026-11-01', '--to' => '2027-01-01'])
            ->expectsOutputToContain('2 created, 0 updated')->assertSuccessful();
        $this->artisan('propertyme:hostex-sync', ['--villa' => $villa->id, '--from' => '2026-11-01', '--to' => '2027-01-01'])
            ->expectsOutputToContain('0 created, 0 updated, 2 unchanged')->assertSuccessful();
        $this->assertDatabaseCount('bookings', 2);
        $booking = Booking::where('hostex_stay_code', 'stay-1')->firstOrFail();
        $this->assertSame('hostex', $booking->source);
        $this->assertSame('3000000.00', $booking->total_price);
        $this->assertSame('90000.00', $booking->hostex_commission_amount);
        $this->assertSame('hostex', $booking->price_source);
        $this->assertSame('Test Guest', $booking->guest_name);
        $this->assertSame('1234', $booking->phone_last4);
        $this->assertSame('14:30:00', $booking->arrival_time);
        $this->assertNull($booking->notes);
        $this->assertNotNull($villa->fresh()->hostex_last_success_at);
        $this->assertNull($villa->fresh()->hostex_last_error);
        $this->assertDatabaseCount('incomes', 0);
        Http::assertSent(fn ($r) => $r->method() === 'GET');
    }

    public function test_changes_update_the_same_booking_and_preserve_manual_notes_and_price(): void
    {
        $villa = $this->villa();
        $row = $this->row();
        $this->remote([$row]);
        $this->sync($villa);
        $booking = Booking::firstOrFail();
        $row['guest_name'] = 'Updated Guest';
        $row['check_out_date'] = '2026-12-05';
        $row['rates']['rate']['amount'] = 4000000;
        $this->remote([$row]);
        $this->assertSame(1, $this->sync($villa)['updated']);
        $this->assertSame('4000000.00', $booking->fresh()->total_price);
        $booking->refresh()->update(['total_price' => 3500000, 'price_source' => 'manual', 'notes' => 'Extra bed']);
        $row['rates']['rate']['amount'] = 4500000;
        $this->remote([$row]);
        $this->sync($villa);
        $booking->refresh();
        $this->assertSame('Updated Guest', $booking->guest_name);
        $this->assertSame('2026-12-05', $booking->check_out->toDateString());
        $this->assertSame('3500000.00', $booking->total_price);
        $this->assertSame('4500000.00', $booking->hostex_rate_amount);
        $this->assertSame('Extra bed', $booking->notes);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_only_explicit_cancellations_cancel_bookings_and_failed_reads_leave_them_intact(): void
    {
        $villa = $this->villa();
        $this->remote([$this->row(), $this->row(['stay_code' => 'stay-2'])]);
        $this->sync($villa);
        $this->remote([$this->row(['status' => 'cancelled'])]);
        $this->assertSame(1, $this->sync($villa)['cancelled']);
        $cancelledAt = Booking::where('hostex_stay_code', 'stay-1')->firstOrFail()->cancelled_at;
        $this->travel(5)->minutes();
        $this->sync($villa);
        $this->assertTrue($cancelledAt->equalTo(Booking::where('hostex_stay_code', 'stay-1')->firstOrFail()->cancelled_at));
        $this->assertSame(BookingStatus::Confirmed, Booking::where('hostex_stay_code', 'stay-2')->firstOrFail()->status);
        $this->remote([]);
        $this->sync($villa);
        $this->remoteCode = 420;
        $this->assertNotNull($this->sync($villa)['error']);
        $this->assertSame(BookingStatus::Confirmed, Booking::where('hostex_stay_code', 'stay-2')->firstOrFail()->status);
        $this->assertStringNotContainsString('sensitive error', $villa->fresh()->hostex_last_error);
    }

    public function test_foreign_currency_is_kept_in_its_original_currency_and_never_mislabeled_as_rupiah(): void
    {
        $villa = $this->villa();
        $row = $this->row(['rates' => ['rate' => ['currency' => 'USD', 'amount' => 200], 'commission' => ['currency' => 'USD', 'amount' => 6]]]);
        $this->remote([$row]);
        $this->assertSame(1, $this->sync($villa)['foreign_currency']);
        $booking = Booking::firstOrFail();
        $this->assertNull($booking->total_price);
        $this->assertSame('USD', $booking->hostex_rate_currency);
        $this->assertSame('200.00', $booking->hostex_rate_amount);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_invalid_rows_roll_back_the_whole_import_and_never_log_guest_data(): void
    {
        $villa = $this->villa();
        $this->remote([$this->row(), $this->row(['stay_code' => 'stay-2', 'guest_name' => 'Private Guest', 'check_in_date' => '2026-11-31'])]);
        $result = $this->sync($villa);
        $this->assertNotNull($result['error']);
        $this->assertSame(0, $result['created']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertNull($villa->fresh()->hostex_last_success_at);
        $this->assertStringNotContainsString('Private Guest', $villa->fresh()->hostex_last_error);
    }

    public function test_active_ical_and_existing_external_booking_references_are_not_silently_duplicated(): void
    {
        $villa = $this->villa();
        $platformId = BookingPlatform::where('slug', 'airbnb')->value('id');
        $channel = $villa->channels()->create(['platform_id' => $platformId, 'ical_url' => 'https://example.test/calendar.ics', 'is_active' => true]);
        $this->assertStringContainsString('Disable', $this->sync($villa)['error']);
        $this->assertSame([], app(IcalSyncService::class)->syncAll());
        Http::assertNothingSent();
        $channel->update(['is_active' => false]);
        Booking::create(['villa_id' => $villa->id, 'platform_id' => $platformId, 'source' => 'manual', 'booking_ref' => 'HMAVORA', 'check_in' => '2026-12-01', 'check_out' => '2026-12-04', 'status' => 'confirmed']);
        $this->remote([$this->row()]);
        $this->assertStringContainsString('already exists outside Hostex', $this->sync($villa)['error']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_hostex_fields_cannot_be_overridden_or_deleted_via_local_edit_and_staff_never_sees_prices(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->villa();
        $villa->staff()->attach($staff);
        $this->remote([$this->row()]);
        $this->sync($villa);
        $booking = Booking::firstOrFail();
        $this->actingAs($master)->put(route('bookings.update', $booking), [
            'check_in' => '2030-01-01', 'check_out' => '2030-01-02', 'status' => 'cancelled', 'guest_name' => 'Local Override',
            'guest_count' => 4, 'total_price' => 3200000, 'notes' => 'Local preparation note',
        ])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('2026-12-01', $booking->check_in->toDateString());
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('Test Guest', $booking->guest_name);
        $this->assertSame('3200000.00', $booking->total_price);
        $this->assertSame('Local preparation note', $booking->notes);
        $this->actingAs($master)->delete(route('bookings.destroy', $booking))->assertForbidden();
        $this->actingAs($master)->get(route('bookings.show', $booking))->assertOk()->assertSee('Hostex API');
        $this->actingAs($master)->get(route('bookings.edit', $booking))->assertOk();
        $events = $this->actingAs($staff)->getJson(route('bookings.events', ['start' => '2026-12-01', 'end' => '2026-12-05', 'villa' => $villa->id]))->assertOk()->getContent();
        $this->assertStringContainsString('Test Guest', $events);
        $this->assertStringNotContainsString('3200000', $events);
        $this->assertStringNotContainsString('90000', $events);
        $this->assertStringNotContainsString('test-hostex-token', $events);
    }

    public function test_sync_button_obeys_villa_access_and_successful_sync_status_is_rendered(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $manager = $this->makeUser(UserRole::Manager);
        $owner = $this->makeUser(UserRole::Owner);
        $villa = $this->villa();
        $this->actingAs($manager)->post(route('sync.run'), ['villa' => $villa->id])->assertForbidden();
        $this->actingAs($owner)->post(route('sync.run'), ['villa' => $villa->id])->assertForbidden();
        Http::assertNothingSent();
        $this->remote([$this->row()]);
        $this->actingAs($master)->post(route('sync.run'), ['villa' => $villa->id])->assertSessionHas('success');
        $this->assertDatabaseCount('bookings', 1);
        $this->actingAs($master)->get(route('villas.show', $villa))->assertOk()->assertSee(__('Sync Hostex now'))->assertDontSee('Tambah link iCal');
        $this->actingAs($master)->get(route('sync.index'))->assertOk()->assertSee('Hostex')->assertSee('Normal');
    }
}
