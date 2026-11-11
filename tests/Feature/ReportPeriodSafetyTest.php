<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Models\Villa;
use App\Notifications\ReportFinalizedNotification;
use App\Services\ExpenseService;
use App\Services\IncomeService;
use App\Services\PayoutImportService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportPeriodSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Villa $villa;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        Notification::fake();
        $this->villa = $this->makeVilla();
        $this->master = $this->makeUser(UserRole::Master);
    }

    private function incomeData(array $overrides = []): array
    {
        return $overrides + [
            'villa_id' => $this->villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'payment_date' => '2026-10-20', 'gross_amount' => 1000000, 'platform_fee' => 30000, 'booking_ref' => 'SAFE-1',
        ];
    }

    private function expenseData(array $overrides = []): array
    {
        return $overrides + [
            'villa_id' => $this->villa->id, 'category_id' => ExpenseCategory::firstOrFail()->id,
            'expense_date' => '2026-10-20', 'description' => 'Monthly expense', 'amount' => 100000,
        ];
    }

    private function close(int $month = 10, ?Villa $villa = null): MonthlyReport
    {
        return MonthlyReport::create(['villa_id' => ($villa ?? $this->villa)->id,
            'year' => 2026, 'month' => $month, 'status' => ReportStatus::Final]);
    }

    private function mustReject(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A service operation must not change a final report period.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_payout_preview_cannot_add_income_after_the_month_is_finalized(): void
    {
        $booking = Booking::create([
            'villa_id' => $this->villa->id, 'platform_id' => $this->incomeData()['platform_id'],
            'source' => 'ical', 'booking_ref' => 'SAFE-1', 'ical_uid' => 'old-feed@example.test',
            'check_in' => '2026-10-01', 'check_out' => '2026-10-04', 'status' => 'needs_completion',
        ])->fresh();
        $before = $booking->getRawOriginal();
        $file = UploadedFile::fake()->createWithContent('payout.csv', "booking_ref,payment_date,gross,platform_fee,guest_name\nSAFE-1,2026-10-20,1000000,30000,Updated Guest\n");
        $service = app(PayoutImportService::class);
        $preview = $service->preview($file, $this->villa->id, $booking->platform_id, 'Y-m-d');
        $this->assertSame('ok', $preview['rows'][0]['status']);
        $this->close();
        $this->mustReject(fn () => $service->import($preview, 'payout.csv', $this->master));
        $this->assertDatabaseCount('incomes', 0);
        $this->assertDatabaseCount('import_batches', 0);
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
    }

    public function test_income_service_rechecks_final_period_without_relying_on_form_validation(): void
    {
        $this->close();
        $this->mustReject(fn () => app(IncomeService::class)->create($this->incomeData(), $this->master));
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_duplicate_payout_preview_cannot_complete_a_booking_in_a_final_period(): void
    {
        $booking = Booking::create([
            'villa_id' => $this->villa->id, 'platform_id' => $this->incomeData()['platform_id'],
            'source' => 'ical', 'booking_ref' => 'SAFE-1', 'ical_uid' => 'duplicate-feed@example.test',
            'check_in' => '2026-10-01', 'check_out' => '2026-10-04', 'status' => 'needs_completion',
        ])->fresh();
        app(IncomeService::class)->create($this->incomeData(), $this->master);
        $before = $booking->getRawOriginal();
        $file = UploadedFile::fake()->createWithContent('payout.csv', "booking_ref,payment_date,gross,platform_fee,guest_name\nSAFE-1,2026-10-20,1000000,30000,Updated Guest\n");
        $service = app(PayoutImportService::class);
        $preview = $service->preview($file, $this->villa->id, $booking->platform_id, 'Y-m-d');
        $this->assertSame('duplicate', $preview['rows'][0]['status']);
        $this->close();
        $this->mustReject(fn () => $service->import($preview, 'payout.csv', $this->master));
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseCount('import_batches', 0);
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
    }

    public function test_payout_requires_a_new_preview_if_the_booking_has_changed_villas(): void
    {
        $booking = Booking::create([
            'villa_id' => $this->villa->id, 'platform_id' => $this->incomeData()['platform_id'],
            'source' => 'ical', 'booking_ref' => 'SAFE-1', 'ical_uid' => 'moved-feed@example.test',
            'check_in' => '2026-10-01', 'check_out' => '2026-10-04', 'status' => 'needs_completion',
        ]);
        $file = UploadedFile::fake()->createWithContent('payout.csv', "booking_ref,payment_date,gross,platform_fee,guest_name\nSAFE-1,2026-10-20,1000000,30000,Updated Guest\n");
        $service = app(PayoutImportService::class);
        $preview = $service->preview($file, $this->villa->id, $booking->platform_id, 'Y-m-d');
        $this->assertSame('ok', $preview['rows'][0]['status']);
        $booking->update(['villa_id' => $this->makeVilla()->id]);
        $before = $booking->fresh()->getRawOriginal();
        $this->mustReject(fn () => $service->import($preview, 'payout.csv', $this->master));
        $this->assertDatabaseCount('incomes', 0);
        $this->assertDatabaseCount('import_batches', 0);
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
    }

    public function test_income_cannot_be_moved_out_of_or_into_a_final_period(): void
    {
        $service = app(IncomeService::class);
        $income = $service->create($this->incomeData(), $this->master);
        $before = $income->fresh()->getRawOriginal();
        $october = $this->close();
        $this->mustReject(fn () => $service->update($income, $this->incomeData(['payment_date' => '2026-11-02'])));
        $this->assertSame($before, $income->fresh()->getRawOriginal());
        $october->update(['status' => ReportStatus::Draft]);
        $this->close(11);
        $this->mustReject(fn () => $service->update($income, $this->incomeData(['payment_date' => '2026-11-02'])));
        $this->assertSame($before, $income->fresh()->getRawOriginal());
    }

    public function test_deleting_income_in_a_final_period_preserves_reference_and_all_amounts(): void
    {
        $service = app(IncomeService::class);
        $income = $service->create($this->incomeData(), $this->master);
        $before = $income->fresh()->getRawOriginal();
        $this->close();
        $this->mustReject(fn () => $service->delete($income));
        $this->assertSame($before, $income->fresh()->getRawOriginal());
    }

    public function test_expense_services_recheck_final_period_on_every_write_path(): void
    {
        $service = app(ExpenseService::class);
        $expense = $service->create($this->expenseData(), $this->master, [])->fresh();
        $before = $expense->getRawOriginal();
        $this->close();
        foreach ([
            fn () => $service->create($this->expenseData(), $this->master, []),
            fn () => $service->update($expense, $this->expenseData(['amount' => 200000]), $this->master, []),
            fn () => $service->delete($expense),
            fn () => $service->markReturned($expense, $this->master, 'Review again'),
            fn () => $service->markValid($expense, $this->master),
        ] as $operation) {
            $this->mustReject($operation);
            $this->assertSame($before, $expense->fresh()->getRawOriginal());
        }
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_bulk_expense_review_rolls_back_if_one_period_is_final(): void
    {
        $openVilla = $this->makeVilla();
        $open = Expense::create($this->expenseData(['villa_id' => $openVilla->id]) + ['status' => ExpenseStatus::Pending, 'created_by' => $this->master->id]);
        $closed = Expense::create($this->expenseData() + ['status' => ExpenseStatus::Pending, 'created_by' => $this->master->id]);
        $this->close();
        $this->mustReject(fn () => app(ExpenseService::class)->bulkValid(collect([$open, $closed]), $this->master));
        $this->assertSame(ExpenseStatus::Pending, $open->fresh()->status);
        $this->assertSame(ExpenseStatus::Pending, $closed->fresh()->status);
    }

    public function test_finalizing_twice_keeps_one_revision_and_sends_one_notification(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $this->villa->update(['owner_id' => $owner->id]);
        $service = app(ReportService::class);
        $first = $service->finalize($this->villa, 2026, 10, $this->master);
        $second = $service->finalize($this->villa, 2026, 10, $this->master);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $second->revision);
        Notification::assertSentToTimes($owner, ReportFinalizedNotification::class, 1);
    }
}
