<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MonthlyReport;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAddition;
use App\Models\Region;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0));
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'villa_id' => $this->makeVilla()->id,
            'category_id' => ExpenseCategory::where('name', 'Gaji Staff')->value('id'),
            'description' => 'Staff Housekeeping', 'amount' => '3.000.000',
            'day_of_month' => 1, 'start_month' => '2026-11', 'is_active' => 1,
        ];
    }

    public function test_master_can_create_and_view_routine_and_it_generates_once(): void
    {
        $master = $this->makeUser(UserRole::Master);
        $this->actingAs($master)->get(route('recurring-expenses.create'))->assertOk();
        $this->post(route('recurring-expenses.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('recurring-expenses.index'));
        $rule = RecurringExpense::firstOrFail();
        $expense = Expense::firstOrFail();
        $this->assertSame(ExpenseStatus::Valid, $expense->status);
        $this->assertSame('3000000.00', $expense->base_amount);
        $this->assertSame('3000000.00', $expense->amount);
        $this->assertSame('2026-11-01', $expense->expense_date->toDateString());
        $this->get(route('recurring-expenses.index'))->assertOk()->assertSee('Staff Housekeeping');
        $this->get(route('recurring-expenses.edit', $rule))->assertOk();
        $this->get(route('expenses.edit', $expense))->assertOk()->assertSee('Tambah lembur');
        $this->get(route('expenses.show', $expense))->assertOk()->assertSee('Rincian pengeluaran rutin');
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(1, Expense::count());
    }

    public function test_overtime_and_notes_only_change_one_transaction_and_total_is_computed_on_server(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload());
        $expense = Expense::firstOrFail();
        $this->put(route('expenses.update', $expense), [
            'villa_id' => $expense->villa_id, 'category_id' => $expense->category_id,
            'expense_date' => '2026-11-01', 'description' => $expense->description,
            'base_amount' => '3000000', 'amount' => '1',
            'additions' => [['label' => 'Lembur Housekeeping', 'amount' => '250000', 'note' => 'Lembur 5 hari × Rp50.000']],
            'notes' => 'Gaji November',
        ])->assertSessionHasNoErrors();
        $this->assertSame('3250000.00', $expense->fresh()->amount);
        $this->assertSame('Lembur 5 hari × Rp50.000', $expense->fresh()->additions[0]['note']);
        $this->assertSame('3000000.00', RecurringExpense::first()->amount);
        $this->get(route('expenses.show', $expense))->assertSee('3.250.000')->assertSee('Lembur Housekeeping');
        $this->get(route('expenses.index'))->assertSee('3.250.000');
        $summary = app(FinanceCalculator::class)->summary(
            FinanceFilter::month(2026, 11, [$expense->villa_id])
        );
        $this->assertSame('3250000.00', $summary->expenses);
        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $december = Expense::whereDate('recurring_period', '2026-12-01')->firstOrFail();
        $this->assertSame('3000000.00', $december->amount);
        $this->assertSame([], $december->additions);
    }

    public function test_base_and_additions_can_be_corrected_and_removed(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload());
        $expense = Expense::firstOrFail();
        $payload = ['villa_id' => $expense->villa_id, 'category_id' => $expense->category_id,
            'expense_date' => '2026-11-01', 'description' => 'Koreksi gaji', 'base_amount' => '2900000'];
        $this->put(route('expenses.update', $expense), $payload + ['additions' => [['label' => 'Lembur', 'amount' => '1234.50']]])->assertSessionHasNoErrors();
        $this->assertSame('2901234.50', $expense->fresh()->amount);
        $this->put(route('expenses.update', $expense), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2900000.00', $expense->fresh()->amount);
        $this->assertSame([], $expense->fresh()->additions);
        $this->put(route('expenses.update', $expense), $payload + ['additions' => [['label' => 'Lembur', 'amount' => '-10']]])->assertSessionHasErrors('additions.0.amount');
        $this->put(route('expenses.update', $expense), $payload + ['additions' => [['label' => 'Lembur', 'amount' => 'bad']]])->assertSessionHasErrors('additions.0.amount');
        $this->put(route('expenses.update', $expense), $payload + ['additions' => [['label' => 'Lembur', 'amount' => '9999999999999']]])->assertSessionHasErrors('base_amount');
    }

    public function test_setting_changes_apply_to_upcoming_months_without_rewriting_old_expenses(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $payload = $this->payload();
        $this->post(route('recurring-expenses.store'), $payload);
        $rule = RecurringExpense::firstOrFail();
        $this->put(route('recurring-expenses.update', $rule), array_replace($payload, ['amount' => '3.500.000']))->assertSessionHasNoErrors();
        $this->assertSame('3000000.00', Expense::first()->amount);
        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame('3500000.00', Expense::latest('id')->first()->amount);
    }

    public function test_scheduler_catches_up_missed_months_and_preserves_deleted_transactions(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['start_month' => '2026-09']));
        $this->assertSame(3, Expense::count());
        $expense = Expense::first();
        $this->delete(route('expenses.destroy', $expense))->assertRedirect();
        $rule = RecurringExpense::first();
        $rule->update(['next_period' => '2026-09-01']);
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(2, Expense::count());
        $this->assertSame(3, Expense::withTrashed()->count());
        $this->delete(route('recurring-expenses.destroy', $rule))->assertRedirect();
        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(3, Expense::withTrashed()->count());
    }

    public function test_short_months_and_end_month_and_no_early_generation(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['start_month' => '2027-01', 'end_month' => '2027-02', 'day_of_month' => 31]));
        $this->assertSame(0, Expense::count());
        $this->travelTo(now()->setDate(2027, 2, 27));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame('2027-01-31', Expense::first()->expense_date->toDateString());
        $this->assertSame(1, Expense::count());
        $this->travelTo(now()->setDate(2027, 3, 31));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(2, Expense::count());
        $this->assertSame('2027-02-28', Expense::latest('id')->first()->expense_date->toDateString());
    }

    public function test_pausing_skips_paused_months_when_resumed(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $payload = $this->payload();
        $this->post(route('recurring-expenses.store'), $payload);
        $rule = RecurringExpense::first();
        $this->put(route('recurring-expenses.update', $rule), array_replace($payload, ['is_active' => 0]))->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2027, 2, 14));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(1, Expense::count());
        $this->put(route('recurring-expenses.update', $rule), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, Expense::count());
        $this->assertSame('2027-02-01', Expense::latest('id')->first()->recurring_period->toDateString());
    }

    public function test_locked_months_are_preserved_but_do_not_block_later_open_months(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['day_of_month' => 20]));
        $rule = RecurringExpense::firstOrFail();
        $report = MonthlyReport::create(['villa_id' => $rule->villa_id, 'year' => 2026, 'month' => 11, 'status' => ReportStatus::Final]);
        $this->travelTo(now()->setDate(2026, 12, 25));
        $this->artisan('propertyme:generate-recurring-expenses')->assertFailed();
        $this->assertSame(1, Expense::count());
        $this->assertSame('2026-12-01', Expense::first()->recurring_period->toDateString());
        $report->update(['status' => ReportStatus::Draft]);
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(2, Expense::count());
        $report->update(['status' => ReportStatus::Final]);
        $november = Expense::whereDate('recurring_period', '2026-11-01')->first();
        $this->get(route('expenses.edit', $november))->assertForbidden();
    }

    public function test_cannot_create_backdated_routines_into_final_reports(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $payload = $this->payload();
        MonthlyReport::create(['villa_id' => $payload['villa_id'], 'year' => 2026, 'month' => 11, 'status' => ReportStatus::Final]);
        $this->post(route('recurring-expenses.store'), $payload)->assertSessionHasErrors('start_month');
        $this->assertSame(0, RecurringExpense::count());
        $this->assertSame(0, Expense::count());
    }

    public function test_generation_uses_villa_local_calendar_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 18:00:00', 'UTC'));
        $this->actingAs($this->makeUser(UserRole::Master));
        $ahead = $this->makeVilla(['timezone' => 'Pacific/Auckland']);
        $behind = $this->makeVilla(['timezone' => 'America/Los_Angeles']);
        $this->post(route('recurring-expenses.store'), $this->payload(['villa_id' => $ahead->id, 'day_of_month' => 15]))->assertSessionHasNoErrors();
        $this->post(route('recurring-expenses.store'), $this->payload(['villa_id' => $behind->id, 'day_of_month' => 15]))->assertSessionHasNoErrors();
        $this->assertSame(1, Expense::count());
        $this->assertSame($ahead->id, Expense::first()->villa_id);
        $this->assertSame('2026-11-15', Expense::first()->expense_date->toDateString());
    }

    public function test_manager_access_is_limited_to_region_and_staff_and_owner_cannot_manage(): void
    {
        $region = Region::create(['name' => 'Wilayah A']);
        $manager = $this->makeUser(UserRole::Manager);
        $manager->regions()->attach($region);
        $ownVilla = $this->makeVilla(['region_id' => $region->id]);
        $outsidePayload = $this->payload(['description' => 'Outside salary']);
        $this->actingAs($this->makeUser(UserRole::Master))->post(route('recurring-expenses.store'), $outsidePayload);
        $outside = RecurringExpense::first();
        $this->actingAs($manager)->get(route('recurring-expenses.index'))->assertOk()->assertDontSee('Outside salary');
        $this->get(route('recurring-expenses.edit', $outside))->assertForbidden();
        $this->put(route('recurring-expenses.update', $outside), $outsidePayload)->assertForbidden();
        $this->delete(route('recurring-expenses.destroy', $outside))->assertForbidden();
        $this->post(route('recurring-expenses.store'), $outsidePayload)->assertForbidden();
        $ownPayload = $this->payload(['villa_id' => $ownVilla->id]);
        $this->post(route('recurring-expenses.store'), $ownPayload)->assertSessionHasNoErrors();
        foreach ([UserRole::Staff, UserRole::Owner] as $role) {
            $this->actingAs($this->makeUser($role))->get(route('recurring-expenses.index'))->assertForbidden();
            $this->post(route('recurring-expenses.store'), $ownPayload)->assertForbidden();
        }
    }

    public function test_resuming_uses_villa_month_at_timezone_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 00:30:00', 'Asia/Makassar'));
        $this->actingAs($this->makeUser(UserRole::Master));
        $villa = $this->makeVilla(['timezone' => 'America/Los_Angeles']);
        $payload = $this->payload(['villa_id' => $villa->id, 'day_of_month' => 30, 'is_active' => 0]);
        $this->post(route('recurring-expenses.store'), $payload)->assertSessionHasNoErrors();
        $rule = RecurringExpense::firstOrFail();
        $this->put(route('recurring-expenses.update', $rule), array_replace($payload, ['is_active' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', Expense::firstOrFail()->recurring_period->toDateString());
    }

    public function test_shortcut_saves_overtime_before_month_end_and_applies_only_to_that_month(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['day_of_month' => 31]));
        $rule = RecurringExpense::firstOrFail();
        $this->get(route('recurring-expenses.index'))->assertOk()->assertSee('Tambah bulan ini');
        $this->get(route('recurring-expenses.additions', $rule))->assertOk()->assertSee('30 November 2026');
        $this->post(route('recurring-expenses.additions.store', $rule), [
            'month' => '2026-11', 'label' => 'Lembur', 'amount' => '200.000', 'note' => 'Tambahan 4 hari',
        ])->assertSessionHasNoErrors()->assertRedirect(route('recurring-expenses.additions', [$rule, 'month' => '2026-11']));
        $this->assertSame(0, Expense::count(), 'Gaji tetap menunggu akhir bulan.');
        $this->assertNull(RecurringExpenseAddition::firstOrFail()->applied_expense_id);
        $this->get(route('recurring-expenses.additions', $rule))->assertOk()->assertSee('3.200.000')->assertSee('Tambahan 4 hari');
        $this->assertSame('0.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 11, [$rule->villa_id]))->expenses);
        $this->travelTo(now()->setDate(2026, 11, 30));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $expense = Expense::firstOrFail();
        $this->assertSame('3200000.00', $expense->amount);
        $this->assertSame('Tambahan 4 hari', $expense->additions[0]['note']);
        $this->assertSame($expense->id, RecurringExpenseAddition::first()->applied_expense_id);
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->assertSame(1, Expense::count());
        $this->assertSame('3200000.00', $expense->fresh()->amount);
        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $december = Expense::whereDate('recurring_period', '2026-12-01')->firstOrFail();
        $this->assertSame('3000000.00', $december->amount);
        $this->assertSame([], $december->additions);
    }

    public function test_shortcut_appends_to_recorded_month_without_losing_existing_corrections(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload());
        $rule = RecurringExpense::firstOrFail();
        $expense = Expense::firstOrFail();
        $expense->update(['base_amount' => '2900000', 'amount' => '2950000',
            'additions' => [['label' => 'Tambahan sebelumnya', 'amount' => '50000', 'note' => 'Tetap disimpan']]]);
        $this->post(route('recurring-expenses.additions.store', $rule), [
            'month' => '2026-11', 'label' => 'Lembur', 'amount' => '200000', 'note' => 'Lembur November',
        ])->assertSessionHasNoErrors();
        $this->assertSame('3150000.00', $expense->fresh()->amount);
        $this->assertCount(2, $expense->fresh()->additions);
        $this->assertSame('Tetap disimpan', $expense->fresh()->additions[0]['note']);
        $this->assertSame('3000000.00', $rule->fresh()->amount);
        $this->assertSame(1, Expense::count());
        $this->assertSame('3150000.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 11, [$rule->villa_id]))->expenses);
    }

    public function test_pending_additions_can_be_removed_and_cannot_be_removed_after_application(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['day_of_month' => 31]));
        $rule = RecurringExpense::firstOrFail();
        $data = ['month' => '2026-11', 'label' => 'Lembur', 'amount' => '200000'];
        $this->post(route('recurring-expenses.additions.store', $rule), $data)->assertSessionHasNoErrors();
        $addition = RecurringExpenseAddition::firstOrFail();
        $this->delete(route('recurring-expenses.additions.destroy', [$rule, $addition]))->assertRedirect();
        $this->assertSame(0, RecurringExpenseAddition::count());
        $this->post(route('recurring-expenses.additions.store', $rule), $data)->assertSessionHasNoErrors();
        $applied = RecurringExpenseAddition::firstOrFail();
        $this->travelTo(now()->setDate(2026, 11, 30));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->delete(route('recurring-expenses.additions.destroy', [$rule, $applied]))->assertSessionHasErrors('month');
        $this->assertSame('3200000.00', Expense::first()->amount);
    }

    public function test_addition_shortcut_respects_final_reports_deleted_entries_and_schedule_range(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['day_of_month' => 31, 'end_month' => '2026-12']));
        $rule = RecurringExpense::firstOrFail();
        $data = ['month' => '2026-11', 'label' => 'Lembur', 'amount' => '200000'];
        $report = MonthlyReport::create(['villa_id' => $rule->villa_id, 'year' => 2026, 'month' => 11, 'status' => ReportStatus::Final]);
        $this->post(route('recurring-expenses.additions.store', $rule), $data)->assertSessionHasErrors('month');
        $this->assertSame(0, RecurringExpenseAddition::count());
        $report->update(['status' => ReportStatus::Draft]);
        foreach (['2026-10', '2027-01'] as $month) {
            $this->post(route('recurring-expenses.additions.store', $rule), array_replace($data, ['month' => $month]))->assertSessionHasErrors('month');
        }
        $this->travelTo(now()->setDate(2026, 11, 30));
        $this->artisan('propertyme:generate-recurring-expenses')->assertSuccessful();
        $this->delete(route('expenses.destroy', Expense::firstOrFail()))->assertRedirect();
        $this->post(route('recurring-expenses.additions.store', $rule), $data)->assertSessionHasErrors('month');
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, RecurringExpenseAddition::count());
    }

    public function test_addition_shortcut_enforces_access_and_rejects_invalid_amounts(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));
        $this->post(route('recurring-expenses.store'), $this->payload(['day_of_month' => 31]));
        $rule = RecurringExpense::firstOrFail();
        $data = ['month' => '2026-11', 'label' => 'Lembur', 'amount' => '200000'];
        foreach (['-200000', '0', 'bad', '9999999999999'] as $amount) {
            $this->post(route('recurring-expenses.additions.store', $rule), array_replace($data, ['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        foreach ([UserRole::Manager, UserRole::Staff, UserRole::Owner] as $role) {
            $this->actingAs($this->makeUser($role))->get(route('recurring-expenses.additions', $rule))->assertForbidden();
            $this->post(route('recurring-expenses.additions.store', $rule), $data)->assertForbidden();
        }
        $this->assertSame(0, RecurringExpenseAddition::count());
    }
}
