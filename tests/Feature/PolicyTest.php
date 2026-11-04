<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Batasan akses ditegakkan di Policy — bukan hanya menyembunyikan tombol. */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function expense(Villa $villa, int $createdBy, ExpenseStatus $status, string $date = '2026-10-10'): Expense
    {
        return Expense::create([
            'villa_id' => $villa->id, 'category_id' => ExpenseCategory::first()->id, 'expense_date' => $date,
            'description' => 'Beli klorin', 'amount' => '150000', 'status' => $status, 'created_by' => $createdBy,
        ]);
    }

    public function test_villa_scope_per_role(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $manager = $this->makeUser(UserRole::Master);
        $mine = $this->makeVilla(['owner_id' => $owner->id]);
        $other = $this->makeVilla();
        $staff->assignedVillas()->attach($other);

        $this->assertEquals([$mine->id], Villa::accessibleBy($owner)->pluck('id')->all());
        $this->assertEquals([$other->id], Villa::accessibleBy($staff)->pluck('id')->all());
        $this->assertCount(2, Villa::accessibleBy($manager)->get());

        $this->assertTrue($owner->can('view', $mine));
        $this->assertFalse($owner->can('view', $other));
        $this->assertFalse($staff->can('viewFinance', $other));
        $this->assertTrue($owner->can('viewFinance', $mine));
    }

    public function test_expense_rules_for_staff(): void
    {
        $staff = $this->makeUser(UserRole::Staff);
        $otherStaff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla();
        $notAssigned = $this->makeVilla();
        $staff->assignedVillas()->attach($villa);
        $otherStaff->assignedVillas()->attach($villa);

        $pending = $this->expense($villa, $staff->id, ExpenseStatus::Pending);
        $returned = $this->expense($villa, $staff->id, ExpenseStatus::Returned);
        $valid = $this->expense($villa, $staff->id, ExpenseStatus::Valid);
        $someoneElses = $this->expense($villa, $otherStaff->id, ExpenseStatus::Pending);

        $this->assertTrue($staff->can('update', $pending));
        $this->assertTrue($staff->can('update', $returned));
        $this->assertFalse($staff->can('update', $valid), 'Nota Valid tidak boleh diubah Staff');
        $this->assertFalse($staff->can('update', $someoneElses), 'Tidak boleh ubah milik orang lain');
        $this->assertFalse($staff->can('view', $someoneElses));
        $this->assertTrue($staff->can('createForVilla', [Expense::class, $villa->id]));
        $this->assertFalse($staff->can('createForVilla', [Expense::class, $notAssigned->id]));
        $this->assertFalse($staff->can('review', $pending));
    }

    public function test_owner_sees_only_valid_expenses_of_own_villa_and_cannot_edit(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla(['owner_id' => $owner->id]);

        $valid = $this->expense($villa, $staff->id, ExpenseStatus::Valid);
        $pending = $this->expense($villa, $staff->id, ExpenseStatus::Pending);

        $this->assertTrue($owner->can('view', $valid));
        $this->assertFalse($owner->can('view', $pending), 'Owner hanya melihat data yang sudah diperiksa');
        $this->assertFalse($owner->can('update', $valid));
        $this->assertFalse($owner->can('delete', $valid));
        $this->assertFalse($owner->can('create', Income::class));
    }

    public function test_final_report_locks_month_for_everyone(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $expense = $this->expense($villa, $manager->id, ExpenseStatus::Valid, '2026-09-15');
        $nextMonth = $this->expense($villa, $manager->id, ExpenseStatus::Valid, '2026-10-01');

        $this->assertTrue($manager->can('update', $expense));

        $report = MonthlyReport::create(['villa_id' => $villa->id, 'year' => 2026, 'month' => 9, 'status' => ReportStatus::Final]);

        $this->assertFalse($manager->can('update', $expense->fresh()), 'Bulan Final terkunci');
        $this->assertTrue($manager->can('update', $nextMonth), 'Bulan lain tidak ikut terkunci');
        $this->assertTrue($manager->can('unlock', $report));
    }

    public function test_owner_only_sees_final_reports_and_staff_none(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla(['owner_id' => $owner->id]);
        $staff->assignedVillas()->attach($villa);

        $draft = MonthlyReport::create(['villa_id' => $villa->id, 'year' => 2026, 'month' => 8, 'status' => ReportStatus::Draft]);
        $final = MonthlyReport::create(['villa_id' => $villa->id, 'year' => 2026, 'month' => 7, 'status' => ReportStatus::Final]);

        $this->assertFalse($owner->can('view', $draft));
        $this->assertTrue($owner->can('view', $final));
        $this->assertFalse($staff->can('view', $final));
        $this->assertFalse($staff->can('viewAny', MonthlyReport::class));
    }

    public function test_booking_rules(): void
    {
        $staff = $this->makeUser(UserRole::Staff);
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $staff->assignedVillas()->attach($villa);

        $ical = Booking::create([
            'villa_id' => $villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'source' => 'ical', 'ical_uid' => 'abc@airbnb', 'check_in' => '2026-11-10', 'check_out' => '2026-11-12', 'status' => 'needs_completion',
        ]);

        $this->assertTrue($staff->can('view', $ical));
        $this->assertFalse($staff->can('viewRevenue', $ical), 'Staff tidak melihat nominal');
        $this->assertFalse($staff->can('update', $ical));
        $this->assertTrue($manager->can('update', $ical));
        $this->assertFalse($manager->can('changeDates', $ical), 'Tanggal dari iCal milik platform');
    }
}
