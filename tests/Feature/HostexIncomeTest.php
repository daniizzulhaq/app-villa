<?php

namespace Tests\Feature;

use App\Enums\FeeBasis;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\Villa;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use App\Services\HostexIncomeService;
use App\Services\HostexSyncService;
use App\Services\IncomeService;
use App\Services\PayoutImportService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HostexIncomeTest extends TestCase
{
    use RefreshDatabase;

    private Villa $villa;

    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'Asia/Makassar'));
        config(['propertyme.channel_manager_provider' => 'hostex', 'services.hostex.access_token' => 'test-token',
            'services.hostex.auto_income_enabled' => true, 'services.hostex.income_date_basis' => 'check_in']);
        $this->villa = $this->makeVilla();
        $this->villa->forceFill(['hostex_property_id' => 501])->save();
        $this->villa->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform, 'effective_from' => '2026-01-01']);
        Http::preventStrayRequests();
        Http::fake(['api.hostex.io/v3/reservations*' => fn () => Http::response([
            'error_code' => 200, 'data' => ['reservations' => $this->rows],
        ])]);
    }

    private function row(array $overrides = []): array
    {
        return array_replace_recursive([
            'stay_code' => 'stay-1', 'reservation_code' => 'order-1', 'property_id' => 501,
            'channel_type' => 'airbnb', 'channel_id' => 'REF-1', 'status' => 'accepted',
            'guest_name' => 'Test Guest', 'number_of_guests' => 2,
            'check_in_date' => '2026-10-30', 'check_out_date' => '2026-11-03',
            'rates' => ['rate' => ['amount' => 3000000, 'currency' => 'IDR'], 'commission' => ['amount' => 90000, 'currency' => 'IDR'],
                'total_rate' => ['amount' => 99000000, 'currency' => 'IDR']],
        ], $overrides);
    }

    private function sync(): array
    {
        return app(HostexSyncService::class)->syncVilla($this->villa->fresh(), '2026-10-01', '2027-01-31');
    }

    private function manual(Booking $booking, array $overrides = []): Income
    {
        return Income::create($overrides + [
            'booking_id' => $booking->id, 'villa_id' => $booking->villa_id, 'platform_id' => $booking->platform_id,
            'booking_ref' => $booking->booking_ref, 'payment_date' => '2026-10-31',
            'gross_amount' => 2500000, 'platform_fee' => 100000, 'net_revenue' => 2400000,
            'fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform, 'fee_base_amount' => 2400000, 'fee_amount' => 360000,
            'notes' => 'Keep manual payout',
        ])->fresh();
    }

    public function test_confirmed_stay_creates_one_income_and_the_calendar_and_report_use_the_same_net(): void
    {
        $this->rows = [$this->row()];
        $first = $this->sync();
        $this->assertNull($first['error']);
        $this->assertSame(1, $first['income_created']);
        $income = Income::firstOrFail();
        $this->assertTrue($income->isFromHostex());
        $this->assertSame('stay-1', $income->hostex_stay_code);
        $this->assertSame('2026-10-30', $income->payment_date->toDateString());
        $this->assertSame('3000000.00', $income->gross_amount);
        $this->assertSame('90000.00', $income->platform_fee);
        $this->assertSame('2910000.00', $income->net_revenue);
        $this->assertSame('436500.00', $income->fee_amount);
        $summary = app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10));
        $this->assertSame('2910000.00', $summary->netRevenue);
        $this->assertSame('2473500.00', $summary->revenueAfterFee);
        $before = $income->getRawOriginal();
        $again = $this->sync();
        $this->assertSame(0, $again['income_created']);
        $this->assertSame(0, $again['income_updated']);
        $this->assertSame($before, $income->fresh()->getRawOriginal());
        $this->assertDatabaseCount('incomes', 1);

        $master = $this->makeUser(UserRole::Master, ['locale' => 'en']);
        $events = $this->actingAs($master)->getJson(route('bookings.events', ['start' => '2026-10-01', 'end' => '2026-12-01']))->assertOk()->json();
        $this->assertSame('Rp 2.910.000', $events[0]['extendedProps']['netRevenue']);
        $this->get(route('incomes.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('Automatic Hostex income');
        $this->get(route('reports.monthly', ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10]))->assertOk()->assertSee('2.473.500');
        $this->get(route('incomes.show', $income))->assertOk()->assertSee('payouts are not verified');
    }

    public function test_enabling_automatic_income_backfills_an_unchanged_existing_booking(): void
    {
        config(['services.hostex.auto_income_enabled' => false]);
        $this->rows = [$this->row()];
        $this->sync();
        $this->assertDatabaseCount('incomes', 0);
        config(['services.hostex.auto_income_enabled' => true]);
        $result = $this->sync();
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame(1, $result['income_created']);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_rate_changes_update_the_existing_income_using_its_original_fee_snapshot(): void
    {
        $this->rows = [$this->row()];
        $this->sync();
        $income = Income::firstOrFail();
        $this->villa->feeHistories()->create(['fee_percentage' => 25, 'fee_basis' => FeeBasis::Gross, 'effective_from' => '2026-09-01']);
        $booking = Booking::firstOrFail();
        $booking->update(['total_price' => 9000000, 'price_source' => 'manual']);
        $this->rows = [$this->row(['rates' => ['rate' => ['amount' => 4000000], 'commission' => ['amount' => 120000]]])];
        $result = $this->sync();
        $this->assertNull($result['error']);
        $this->assertSame(1, $result['income_updated']);
        $saved = $income->fresh();
        $this->assertSame('4000000.00', $saved->gross_amount);
        $this->assertSame('3880000.00', $saved->net_revenue);
        $this->assertSame('15.00', $saved->fee_percentage);
        $this->assertSame(FeeBasis::NetOfPlatform, $saved->fee_basis);
        $this->assertSame('582000.00', $saved->fee_amount);
        $this->assertSame('9000000.00', $booking->fresh()->total_price);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_cancellation_archives_automatic_revenue_and_reactivation_restores_the_same_entry(): void
    {
        $this->rows = [$this->row()];
        $this->sync();
        $income = Income::firstOrFail();
        $this->rows = [$this->row(['status' => 'cancelled'])];
        $result = $this->sync();
        $this->assertSame(1, $result['income_voided']);
        $this->assertSame(0, Income::count());
        $this->assertTrue($income->fresh()->trashed());
        $this->assertSame('0.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10))->netRevenue);
        $this->rows = [$this->row()];
        $result = $this->sync();
        $this->assertSame(1, $result['income_updated']);
        $this->assertSame($income->id, Income::firstOrFail()->id);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_incomplete_or_foreign_amounts_are_not_invented_and_require_review(): void
    {
        $missing = $this->row(['stay_code' => 'missing', 'channel_id' => 'MISSING']);
        unset($missing['rates']['commission']);
        $this->rows = [$missing,
            $this->row(['stay_code' => 'usd', 'channel_id' => 'USD', 'rates' => ['rate' => ['currency' => 'USD'], 'commission' => ['currency' => 'USD']]]),
            $this->row(['stay_code' => 'invalid', 'channel_id' => 'INVALID', 'rates' => ['commission' => ['amount' => 4000000]]]),
        ];
        $result = $this->sync();
        $this->assertNull($result['error']);
        $this->assertSame(3, $result['income_pending']);
        $this->assertDatabaseCount('incomes', 0);
        $this->assertSame(['currency', 'invalid_amounts', 'missing_amounts'], Booking::orderBy('hostex_income_error')->pluck('hostex_income_error')->all());
        $this->assertNotEmpty(app(ReportService::class)->blockers($this->villa, 2026, 10));
    }

    public function test_zero_commission_and_free_bookings_are_valid_but_pending_stays_are_not_revenue(): void
    {
        $this->rows = [$this->row(['rates' => ['commission' => ['amount' => 0]]]),
            $this->row(['stay_code' => 'free', 'channel_id' => 'FREE', 'rates' => ['rate' => ['amount' => 0], 'commission' => ['amount' => 0]]]),
            $this->row(['stay_code' => 'pending', 'channel_id' => 'PENDING', 'status' => 'wait_pay']),
        ];
        $result = $this->sync();
        $this->assertSame(2, $result['income_created']);
        $this->assertSame(0, $result['income_pending']);
        $this->assertSame('3000000.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10))->netRevenue);
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_existing_manual_income_is_preserved_and_deleted_manual_income_is_not_recreated(): void
    {
        config(['services.hostex.auto_income_enabled' => false]);
        $this->rows = [$this->row()];
        $this->sync();
        $manual = $this->manual(Booking::firstOrFail());
        $before = $manual->getRawOriginal();
        config(['services.hostex.auto_income_enabled' => true]);
        $result = $this->sync();
        $this->assertSame(0, $result['income_created']);
        $this->assertSame(0, $result['income_pending']);
        $this->assertSame($before, $manual->fresh()->getRawOriginal());
        $manual->delete();
        $result = $this->sync();
        $this->assertSame(1, $result['income_pending']);
        $this->assertSame('deleted_income', Booking::firstOrFail()->hostex_income_error);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_final_month_blocks_creation_updates_and_cancellation_until_unlocked(): void
    {
        $report = MonthlyReport::create(['villa_id' => $this->villa->id, 'year' => 2026, 'month' => 10, 'status' => ReportStatus::Final]);
        $this->rows = [$this->row()];
        $result = $this->sync();
        $this->assertSame(1, $result['income_pending']);
        $this->assertDatabaseCount('incomes', 0);
        $report->update(['status' => ReportStatus::Draft]);
        $this->sync();
        $income = Income::firstOrFail();
        $before = $income->getRawOriginal();
        $report->update(['status' => ReportStatus::Final]);
        $same = $this->sync();
        $this->assertSame(0, $same['income_pending']);
        $this->rows = [$this->row(['rates' => ['rate' => ['amount' => 4000000]]])];
        $this->assertSame(1, $this->sync()['income_pending']);
        $this->assertSame($before, $income->fresh()->getRawOriginal());
        $this->rows = [$this->row(['status' => 'cancelled'])];
        $this->assertSame(1, $this->sync()['income_pending']);
        $this->assertFalse($income->fresh()->trashed());
        $report->update(['status' => ReportStatus::Draft]);
        $this->assertSame(1, $this->sync()['income_voided']);
        $this->assertTrue($income->fresh()->trashed());
    }

    public function test_checkout_date_basis_groups_cross_month_stays_into_the_selected_period(): void
    {
        config(['services.hostex.income_date_basis' => 'check_out']);
        $this->rows = [$this->row()];
        $this->sync();
        $this->assertSame('2026-11-03', Income::firstOrFail()->payment_date->toDateString());
        $this->assertSame('check_out', Income::firstOrFail()->hostex_date_basis);
        $calc = app(FinanceCalculator::class);
        $this->assertSame('0.00', $calc->summary(FinanceFilter::month(2026, 10))->netRevenue);
        $this->assertSame('2910000.00', $calc->summary(FinanceFilter::month(2026, 11))->netRevenue);
    }

    public function test_multiple_stays_in_one_order_are_counted_once_per_stay_without_repeating_order_totals(): void
    {
        $this->rows = [$this->row(), $this->row(['stay_code' => 'stay-2'])];
        $result = $this->sync();
        $this->assertNull($result['error']);
        $this->assertSame(2, $result['income_created']);
        $this->assertSame(2, Income::count());
        $this->assertSame('6000000.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10))->gross);
        $this->assertSame(0, $this->sync()['income_updated']);
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_automatic_income_cannot_be_manually_edited_deleted_or_recorded_twice(): void
    {
        $this->rows = [$this->row()];
        $this->sync();
        $master = $this->makeUser(UserRole::Master);
        $income = Income::firstOrFail();
        $this->actingAs($master)->get(route('incomes.edit', $income))->assertForbidden();
        $this->delete(route('incomes.destroy', $income))->assertForbidden();
        $payload = $income->getAttributes();
        foreach ([fn () => app(IncomeService::class)->create($payload, $master),
            fn () => app(IncomeService::class)->update($income, $payload),
            fn () => app(IncomeService::class)->delete($income)] as $operation) {
            try {
                $operation();
                $this->fail('Automatic income must remain controlled by Hostex.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('incomes', 1);
            }
        }
    }

    public function test_incomplete_new_data_retracts_old_automatic_amounts_then_restores_the_same_row(): void
    {
        $this->rows = [$this->row()];
        $this->sync();
        $income = Income::firstOrFail();
        $missing = $this->row();
        unset($missing['rates']['commission']);
        $this->rows = [$missing];
        $result = $this->sync();
        $this->assertSame(1, $result['income_voided']);
        $this->assertSame(1, $result['income_pending']);
        $this->assertSame(0, Income::count());
        $this->rows = [$this->row()];
        $this->sync();
        $this->assertSame($income->id, Income::firstOrFail()->id);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_invalid_stay_rolls_back_both_booking_and_income_changes_for_the_villa(): void
    {
        $this->rows = [$this->row(), $this->row(['stay_code' => 'invalid', 'status' => 'unsupported'])];
        $result = $this->sync();
        $this->assertNotNull($result['error']);
        $this->assertSame(0, $result['income_created']);
        $this->assertDatabaseCount('incomes', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_date_changes_cannot_modify_an_old_final_month_or_a_new_final_month(): void
    {
        $this->rows = [$this->row()];
        $this->sync();
        $income = Income::firstOrFail();
        $october = MonthlyReport::create(['villa_id' => $this->villa->id, 'year' => 2026, 'month' => 10, 'status' => ReportStatus::Final]);
        $this->rows = [$this->row(['check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-05'])];
        $this->assertSame(1, $this->sync()['income_pending']);
        $this->assertSame('2026-10-30', $income->fresh()->payment_date->toDateString());
        $october->update(['status' => ReportStatus::Draft]);
        $this->assertNotEmpty(app(ReportService::class)->blockers($this->villa, 2026, 10), 'The old pending income must block re-finalization.');
        $november = MonthlyReport::create(['villa_id' => $this->villa->id, 'year' => 2026, 'month' => 11, 'status' => ReportStatus::Final]);
        $this->assertSame(1, $this->sync()['income_pending']);
        $november->update(['status' => ReportStatus::Draft]);
        $this->assertSame(1, $this->sync()['income_updated']);
        $this->assertSame('2026-11-01', $income->fresh()->payment_date->toDateString());
        $this->assertSame('0.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10))->netRevenue);
    }

    public function test_custom_fee_requires_manual_income_and_the_existing_manual_entry_remains_authoritative(): void
    {
        $this->villa->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => FeeBasis::Custom, 'effective_from' => '2026-10-01']);
        $this->rows = [$this->row()];
        $this->assertSame(1, $this->sync()['income_pending']);
        $this->assertSame('custom_fee', Booking::firstOrFail()->hostex_income_error);
        $manual = $this->manual(Booking::firstOrFail());
        $before = $manual->getRawOriginal();
        $this->assertSame(0, $this->sync()['income_pending']);
        $this->assertSame($before, $manual->fresh()->getRawOriginal());
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_payout_import_does_not_duplicate_automatic_revenue_even_if_the_reference_is_empty(): void
    {
        $this->rows = [$this->row()];
        $this->sync();
        $income = Income::firstOrFail();
        $income->forceFill(['booking_ref' => null])->save();
        $actor = $this->makeUser(UserRole::Master);
        $file = UploadedFile::fake()->createWithContent('payout.csv', "check_in,check_out,payment_date,gross,platform_fee\n2026-10-30,2026-11-03,2026-11-05,2500000,100000\n");
        $service = app(PayoutImportService::class);
        $preview = $service->preview($file, $this->villa->id, $income->platform_id, 'Y-m-d');
        $this->assertSame('duplicate', $preview['rows'][0]['status']);
        $this->assertFalse($preview['rows'][0]['sets_price']);
        $before = $income->fresh()->getRawOriginal();
        $bookingBefore = Booking::first()->getRawOriginal();
        $batch = $service->import($preview, 'payout.csv', $actor);
        $this->assertSame(0, $batch->rows_imported);
        $this->assertSame($before, $income->fresh()->getRawOriginal());
        $this->assertSame($bookingBefore, Booking::first()->getRawOriginal());
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_owner_staff_and_other_managers_remain_scoped_and_exports_include_automatic_revenue(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $outside = $this->makeUser(UserRole::Manager);
        $master = $this->makeUser(UserRole::Master);
        $this->villa->update(['owner_id' => $owner->id]);
        $this->villa->staff()->attach($staff);
        $this->rows = [$this->row()];
        $this->sync();
        $income = Income::firstOrFail();
        $this->actingAs($owner)->get(route('incomes.show', $income))->assertOk();
        $this->actingAs($outside)->get(route('incomes.show', $income))->assertForbidden();
        $this->actingAs($staff)->get(route('incomes.show', $income))->assertForbidden();
        $events = $this->getJson(route('bookings.events', ['start' => '2026-10-01', 'end' => '2026-12-01']))->assertOk()->json();
        $this->assertArrayNotHasKey('netRevenue', $events[0]['extendedProps']);
        $params = ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10];
        $pdf = $this->actingAs($master)->get(route('reports.pdf', $params))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get(route('reports.excel', $params))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_payout_import_does_not_guess_between_two_automatic_stays_with_the_same_dates(): void
    {
        $this->rows = [$this->row(), $this->row(['stay_code' => 'stay-2', 'channel_id' => 'REF-2'])];
        $this->sync();
        $file = UploadedFile::fake()->createWithContent('payout.csv', "check_in,check_out,payment_date,gross,platform_fee\n2026-10-30,2026-11-03,2026-11-05,3000000,90000\n");
        $preview = app(PayoutImportService::class)->preview($file, $this->villa->id, Income::first()->platform_id, 'Y-m-d');
        $this->assertSame('error', $preview['rows'][0]['status']);
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_manual_income_with_an_unlinked_matching_reference_is_not_duplicated(): void
    {
        config(['services.hostex.auto_income_enabled' => false]);
        $this->rows = [$this->row()];
        $this->sync();
        $manual = $this->manual(Booking::firstOrFail(), ['booking_id' => null]);
        $before = $manual->getRawOriginal();
        config(['services.hostex.auto_income_enabled' => true]);
        $result = $this->sync();
        $this->assertSame(0, $result['income_created']);
        $this->assertSame(1, $result['income_pending']);
        $this->assertSame('unlinked_income', Booking::firstOrFail()->hostex_income_error);
        $this->assertSame($before, $manual->fresh()->getRawOriginal());
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_income_projection_cannot_touch_manual_or_ical_booking_income(): void
    {
        config(['services.hostex.auto_income_enabled' => false]);
        $this->rows = [$this->row()];
        $this->sync();
        $booking = Booking::firstOrFail();
        $manual = $this->manual($booking);
        $before = $manual->getRawOriginal();
        $booking->forceFill(['source' => 'ical', 'hostex_stay_code' => null, 'hostex_status' => null])->save();
        config(['services.hostex.auto_income_enabled' => true]);
        $result = app(HostexIncomeService::class)->sync($booking);
        $this->assertSame(0, $result['income_voided']);
        $this->assertSame(0, $result['income_created']);
        $this->assertSame($before, $manual->fresh()->getRawOriginal());
    }
}
