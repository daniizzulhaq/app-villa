<?php

namespace Tests\Feature;

use App\Enums\FeeBasis;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Models\Villa;
use App\Services\HostexIncomeService;
use App\Services\IncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class HostexIncomeRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        config(['propertyme.channel_manager_provider' => 'hostex', 'services.hostex.auto_income_enabled' => true,
            'services.hostex.income_date_basis' => 'check_in']);
        Http::preventStrayRequests();
        $this->manager = $this->makeUser(UserRole::Master);
        $this->booking = $this->hostexBooking($this->makeVilla());
    }

    private function hostexBooking(Villa $villa): Booking
    {
        $booking = new Booking;
        $booking->forceFill([
            'villa_id' => $villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'source' => 'hostex', 'status' => 'confirmed', 'hostex_status' => 'accepted',
            'hostex_stay_code' => 'airbnb-'.uniqid(), 'booking_ref' => 'REF-'.uniqid(),
            'check_in' => '2026-11-13', 'check_out' => '2026-11-17', 'guest_name' => 'Rica Recovery',
            'hostex_rate_amount' => '9340660.00', 'hostex_rate_currency' => 'IDR',
            'hostex_commission_amount' => '1607060.00', 'hostex_commission_currency' => 'IDR',
        ])->save();

        return $booking;
    }

    private function deletedManual(?Booking $booking = null): Income
    {
        $booking ??= $this->booking;
        $income = app(IncomeService::class)->create([
            'booking_id' => $booking->id, 'villa_id' => $booking->villa_id, 'platform_id' => $booking->platform_id,
            'payment_date' => '2026-10-30', 'gross_amount' => '8500000', 'platform_fee' => '1000000',
            'notes' => 'Preserve the original note',
        ], $this->manager);
        app(IncomeService::class)->delete($income);
        app(HostexIncomeService::class)->sync($booking->fresh());

        return $income->fresh();
    }

    private function payload(Income $income): array
    {
        $review = $this->actingAs($this->manager)->get(route('bookings.income-recovery', $this->booking))->assertOk();

        return ['income_id' => $income->id, 'review_token' => $review->viewData('tokens')[$income->id] ?? str_repeat('0', 64)];
    }

    public function test_review_is_read_only_and_restore_reuses_the_manual_entry_with_hostex_amounts(): void
    {
        $income = $this->deletedManual();
        $this->assertSame('deleted_income', $this->booking->fresh()->hostex_income_error);
        $before = $income->getRawOriginal();
        $this->actingAs($this->manager)->get(route('bookings.show', $this->booking))->assertOk()->assertSee(route('bookings.income-recovery', $this->booking));
        $payload = $this->payload($income);
        $this->assertSame($before, $income->fresh()->getRawOriginal());
        // A later villa fee change must not overwrite the original income snapshot.
        $this->booking->villa->update(['fee_percentage' => 30]);
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $this->booking));
        $restored = $income->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertSame($income->id, $restored->id);
        $this->assertSame('9340660.00', $restored->gross_amount);
        $this->assertSame('1607060.00', $restored->platform_fee);
        $this->assertSame('7733600.00', $restored->net_revenue);
        $this->assertSame('1160040.00', $restored->fee_amount);
        $this->assertSame('15.00', $restored->fee_percentage);
        $this->assertSame('2026-11-13', $restored->payment_date->toDateString());
        $this->assertSame('Preserve the original note', $restored->notes);
        $this->assertSame($this->booking->hostex_stay_code, $restored->hostex_stay_code);
        $this->assertNull($restored->hostex_void_reason);
        $this->assertNull($this->booking->fresh()->hostex_income_error);
        $this->assertSame(1, Income::withTrashed()->count());
        $this->assertSame(0, app(HostexIncomeService::class)->sync($this->booking->fresh())['income_created']);
        $this->assertTrue(Audit::where('auditable_type', Income::class)->where('auditable_id', $income->id)->where('event', 'restored')->exists());
        Http::assertNothingSent();
    }

    public function test_deleted_automatic_income_requires_review_and_is_restored_once(): void
    {
        app(HostexIncomeService::class)->sync($this->booking);
        $income = Income::firstOrFail();
        $income->delete();
        $this->assertSame(1, app(HostexIncomeService::class)->sync($this->booking->fresh())['income_pending']);
        $payload = $this->payload($income);
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($income->fresh()->trashed());
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->assertSame(1, Income::withTrashed()->count());
        $this->assertSame(1, Income::count());
    }

    public function test_final_old_or_new_report_month_blocks_every_recovery_write(): void
    {
        $income = $this->deletedManual();
        $payload = $this->payload($income);
        $before = $income->getRawOriginal();
        foreach ([10, 11] as $month) {
            $report = MonthlyReport::create(['villa_id' => $this->booking->villa_id, 'year' => 2026, 'month' => $month, 'status' => ReportStatus::Final]);
            $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
            $this->assertSame($before, $income->fresh()->getRawOriginal());
            $this->assertSame('deleted_income', $this->booking->fresh()->hostex_income_error);
            $this->assertSame(ReportStatus::Final, $report->fresh()->status);
            $report->update(['status' => ReportStatus::Draft]);
        }
    }

    public function test_other_matching_entries_block_recovery_and_no_records_are_detached(): void
    {
        $income = $this->deletedManual();
        $other = $this->deletedManual();
        $before = Income::withTrashed()->orderBy('id')->get()->map->getRawOriginal()->all();
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $this->payload($income))->assertSessionHasErrors('income');
        $this->assertSame($before, Income::withTrashed()->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($this->booking->id, $other->fresh()->booking_id);
    }

    public function test_stale_review_and_incomplete_or_cancelled_booking_cannot_be_restored(): void
    {
        $income = $this->deletedManual();
        $payload = $this->payload($income);
        $before = $income->getRawOriginal();
        $this->booking->forceFill(['hostex_rate_amount' => '9340661.00'])->save();
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->booking->forceFill(['hostex_rate_amount' => '9340660.00', 'hostex_commission_amount' => null])->save();
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->booking->forceFill(['hostex_commission_amount' => '1607060.00', 'hostex_status' => 'cancelled'])->save();
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->assertSame($before, $income->fresh()->getRawOriginal());
    }

    public function test_owner_staff_and_unassigned_manager_cannot_review_or_restore(): void
    {
        $income = $this->deletedManual();
        $payload = $this->payload($income);
        foreach ([UserRole::Owner, UserRole::Staff, UserRole::Manager] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user)->get(route('bookings.income-recovery', $this->booking))->assertForbidden();
            $this->actingAs($user)->post(route('bookings.recover-income', $this->booking), $payload)->assertForbidden();
        }
        $this->assertTrue($income->fresh()->trashed());
    }

    public function test_another_villa_or_booking_income_id_is_rejected(): void
    {
        $income = $this->deletedManual();
        $other = $this->deletedManual($this->hostexBooking($this->makeVilla()));
        $payload = $this->payload($income);
        $payload['income_id'] = $other->id;
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->assertTrue($income->fresh()->trashed());
        $this->assertTrue($other->fresh()->trashed());
    }

    public function test_custom_basis_and_foreign_currency_keep_deleted_records_unchanged(): void
    {
        $income = $this->deletedManual();
        $payload = $this->payload($income);
        $this->booking->forceFill(['hostex_rate_currency' => 'USD'])->save();
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->booking->forceFill(['hostex_rate_currency' => 'IDR'])->save();
        $income->update(['fee_basis' => FeeBasis::Custom]);
        $this->actingAs($this->manager)->post(route('bookings.recover-income', $this->booking), $payload)->assertSessionHasErrors('income');
        $this->assertTrue($income->fresh()->trashed());
        $this->assertNull($income->fresh()->hostex_stay_code);
    }
}
