<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarNetRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function hostexBooking(Villa $villa, array $fields = []): Booking
    {
        $booking = new Booking([
            'villa_id' => $villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'source' => 'hostex', 'check_in' => '2026-12-01', 'check_out' => '2026-12-04',
            'status' => 'confirmed', 'guest_name' => 'Calendar Guest', 'notes' => 'Operational note',
            'total_price' => 9500000, 'price_source' => 'manual',
        ]);
        $booking->forceFill($fields + [
            'hostex_stay_code' => 'calendar-'.uniqid(), 'hostex_rate_currency' => 'IDR', 'hostex_rate_amount' => 3000000,
            'hostex_commission_currency' => 'IDR', 'hostex_commission_amount' => 90000,
        ])->save();

        return $booking;
    }

    private function events(): string
    {
        return route('bookings.events', ['start' => '2026-12-01', 'end' => '2027-01-01']);
    }

    public function test_master_and_owner_see_hostex_net_after_commission_without_using_manual_gross_or_deducting_management_fee(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $owner = $this->makeUser(UserRole::Owner);
        $villa = $this->makeVilla(['owner_id' => $owner->id, 'fee_percentage' => 15]);
        $booking = $this->hostexBooking($villa);
        foreach ([$master, $owner] as $user) {
            $json = $this->actingAs($user)->getJson($this->events())->assertOk()->json();
            $this->assertSame((string) $booking->id, $json[0]['id']);
            $this->assertSame('Rp 2.910.000', $json[0]['extendedProps']['netRevenue']);
            $this->assertSame(__('Setelah komisi OTA, sebelum management fee.'), $json[0]['extendedProps']['netRevenueHint']);
            $this->actingAs($user)->get(route('bookings.calendar'))->assertOk()
                ->assertSee('selected.netRevenue ??', false)->assertDontSee('selected.notes ||', false);
        }
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_staff_payload_has_no_net_revenue_and_preserves_operational_notes_while_owners_remain_scoped(): void
    {
        $staff = $this->makeUser(UserRole::Staff);
        $owner = $this->makeUser(UserRole::Owner);
        $villa = $this->makeVilla(['owner_id' => $owner->id]);
        $villa->staff()->attach($staff);
        $booking = $this->hostexBooking($villa);
        $this->hostexBooking($this->makeVilla(), ['hostex_rate_amount' => 8888888]);
        $json = $this->actingAs($staff)->getJson($this->events())->assertOk()->json();
        $this->assertCount(1, $json);
        $this->assertSame('Operational note', $json[0]['extendedProps']['notes']);
        $this->assertArrayNotHasKey('netRevenue', $json[0]['extendedProps']);
        $this->assertArrayNotHasKey('netRevenueHint', $json[0]['extendedProps']);
        $this->assertStringNotContainsString('2.910.000', json_encode($json));
        $this->assertStringNotContainsString('9500000', json_encode($json));
        $this->actingAs($staff)->get(route('staff.calendar'))->assertOk()
            ->assertSee('selected.notes ||', false)->assertDontSee('selected.netRevenue ??', false);
        $json = $this->actingAs($owner)->getJson($this->events())->assertOk()->json();
        $this->assertCount(1, $json);
        $this->assertSame((string) $booking->id, $json[0]['id']);
    }

    public function test_foreign_currency_zero_fees_missing_fees_and_mismatched_currencies_are_distinguished(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $usd = $this->hostexBooking($villa, ['hostex_rate_currency' => 'USD', 'hostex_rate_amount' => '500.50', 'hostex_commission_currency' => 'USD', 'hostex_commission_amount' => '15.05']);
        $freeCommission = $this->hostexBooking($villa, ['hostex_rate_amount' => 1000000, 'hostex_commission_amount' => 0]);
        $zero = $this->hostexBooking($villa, ['hostex_rate_amount' => 0, 'hostex_commission_amount' => 0]);
        $missing = $this->hostexBooking($villa, ['hostex_commission_amount' => null]);
        $mismatch = $this->hostexBooking($villa, ['hostex_commission_currency' => 'USD']);
        $json = collect($this->actingAs($master)->getJson($this->events())->assertOk()->json())->keyBy('id');
        $this->assertSame('USD 485.45', $json[$usd->id]['extendedProps']['netRevenue']);
        $this->assertSame('Rp 1.000.000', $json[$freeCommission->id]['extendedProps']['netRevenue']);
        $this->assertSame('Rp 0', $json[$zero->id]['extendedProps']['netRevenue']);
        $this->assertNull($json[$missing->id]['extendedProps']['netRevenue']);
        $this->assertNull($json[$mismatch->id]['extendedProps']['netRevenue']);
    }

    public function test_recorded_net_income_is_used_for_legacy_bookings_without_subtracting_ota_commission_twice(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $booking = $this->hostexBooking($villa, ['source' => 'manual']);
        Income::create([
            'booking_id' => $booking->id, 'villa_id' => $villa->id, 'platform_id' => $booking->platform_id,
            'payment_date' => '2026-12-01', 'gross_amount' => 1000000, 'platform_fee' => 100000,
            'net_revenue' => 900000, 'fee_percentage' => 15, 'fee_basis' => $villa->fee_basis,
            'fee_base_amount' => 900000, 'fee_amount' => 135000,
        ]);
        $unrecorded = $this->hostexBooking($villa, ['source' => 'ical']);
        $json = collect($this->actingAs($master)->getJson($this->events())->assertOk()->json())->keyBy('id');
        $this->assertSame('Rp 900.000', $json[$booking->id]['extendedProps']['netRevenue']);
        $this->assertSame(__('Dari pemasukan tercatat, sebelum management fee.'), $json[$booking->id]['extendedProps']['netRevenueHint']);
        $this->assertNull($json[$unrecorded->id]['extendedProps']['netRevenue']);
    }
}
