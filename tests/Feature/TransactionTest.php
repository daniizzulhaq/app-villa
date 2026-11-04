<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\FeeBasis;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Notifications\ExpenseReturnedNotification;
use App\Notifications\ExpenseSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function airbnb(): int
    {
        return BookingPlatform::where('slug', 'airbnb')->value('id');
    }

    private function incomePayload(int $villaId, array $o = []): array
    {
        return $o + ['villa_id' => $villaId, 'platform_id' => $this->airbnb(), 'payment_date' => '2026-10-15',
            'gross_amount' => '80.000.000', 'platform_fee' => '3000000', 'other_deduction' => '0', 'booking_ref' => 'HM1'];
    }

    public function test_income_snapshot_uses_fee_effective_on_payment_date(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla(['fee_percentage' => 20]);
        $villa->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform, 'effective_from' => '2026-01-01']);
        $villa->feeHistories()->create(['fee_percentage' => 20, 'fee_basis' => FeeBasis::NetOfPlatform, 'effective_from' => '2026-11-01']);

        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id))->assertSessionHasNoErrors();

        $income = Income::firstOrFail();
        $this->assertSame('15.00', (string) $income->fee_percentage, 'Oktober masih memakai fee 15%');
        $this->assertSame('77000000.00', (string) $income->net_revenue);
        $this->assertSame('11550000.00', (string) $income->fee_amount);

        // Mengubah fee villa sekarang tidak mengubah pemasukan lama
        $villa->update(['fee_percentage' => 30]);
        $this->assertSame('11550000.00', (string) $income->fresh()->fee_amount);
    }

    public function test_duplicate_booking_ref_and_locked_month_are_rejected(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $villa->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform, 'effective_from' => '2026-01-01']);

        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id))->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id))->assertSessionHasErrors('booking_ref');

        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id, ['booking_ref' => 'X', 'platform_fee' => '90000000']))
            ->assertSessionHasErrors('platform_fee');

        MonthlyReport::create(['villa_id' => $villa->id, 'year' => 2026, 'month' => 10, 'status' => ReportStatus::Final]);
        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id, ['booking_ref' => 'HM2']))->assertSessionHasErrors('payment_date');
        $this->actingAs($manager)->delete(route('incomes.destroy', Income::first()))->assertForbidden();
    }

    public function test_income_access_per_role_and_soft_delete_frees_ref(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla(['owner_id' => $owner->id]);
        $staff->assignedVillas()->attach($villa);
        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id));
        $income = Income::firstOrFail();

        $this->actingAs($owner)->get(route('incomes.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('80.000.000');
        $this->actingAs($owner)->get(route('incomes.show', $income))->assertOk();
        $this->actingAs($owner)->get(route('incomes.edit', $income))->assertForbidden();
        $this->actingAs($staff)->get(route('incomes.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('incomes.create'))->assertOk();
        $this->actingAs($manager)->get(route('incomes.edit', $income))->assertOk();

        $this->actingAs($manager)->delete(route('incomes.destroy', $income))->assertRedirect();
        $this->assertSoftDeleted($income);
        $this->actingAs($manager)->post(route('incomes.store'), $this->incomePayload($villa->id))->assertSessionHasNoErrors();
    }

    public function test_staff_uploads_receipt_compressed_private_and_manager_is_notified(): void
    {
        Storage::fake('local');
        Notification::fake();
        $manager = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);
        $otherStaff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla();
        $notMine = $this->makeVilla();
        $staff->assignedVillas()->attach($villa);
        $otherStaff->assignedVillas()->attach($villa);
        $cat = ExpenseCategory::where('name', 'Perawatan Kolam')->first();

        // Wajib foto
        $this->actingAs($staff)->post(route('expenses.store'), ['villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '150.000'])
            ->assertSessionHasErrors('receipts');
        // Villa tidak ditugaskan
        $this->actingAs($staff)->post(route('expenses.store'), ['villa_id' => $notMine->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '150000',
            'receipts' => [UploadedFile::fake()->image('n.jpg')]])->assertForbidden();

        $this->actingAs($staff)->post(route('expenses.store'), [
            'villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '150.000',
            'receipts' => [UploadedFile::fake()->image('nota1.jpg', 4000, 3000), UploadedFile::fake()->image('nota2.png', 800, 600)],
        ])->assertRedirect(route('staff.activity'));

        $e = Expense::with('receipts')->firstOrFail();
        $this->assertSame(ExpenseStatus::Pending, $e->status);
        $this->assertSame('Perawatan Kolam', $e->description, 'Keterangan default = nama kategori');
        $this->assertSame('150000.00', (string) $e->amount);
        $this->assertCount(2, $e->receipts);
        Storage::disk('local')->assertExists($e->receipts[0]->path);
        $this->assertStringStartsWith('receipts/', $e->receipts[0]->path);
        Notification::assertSentTo($manager, ExpenseSubmittedNotification::class);

        // Foto hanya lewat route yang dicek Policy
        $this->actingAs($staff)->get(route('files.receipt', $e->receipts[0]))->assertOk();
        $this->actingAs($manager)->get(route('files.receipt', $e->receipts[0]))->assertOk();
        $this->actingAs($otherStaff)->get(route('files.receipt', $e->receipts[0]))->assertForbidden();

        $this->actingAs($staff)->get(route('staff.activity'))->assertOk()->assertSee('Menunggu pemeriksaan');
        $this->actingAs($staff)->get(route('expenses.create'))->assertOk()->assertSee('capture="environment"', false);
    }

    public function test_review_flow_return_resubmit_valid_and_owner_visibility(): void
    {
        Storage::fake('local');
        Notification::fake();
        $manager = $this->makeUser(UserRole::Master);
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla(['owner_id' => $owner->id]);
        $staff->assignedVillas()->attach($villa);
        $cat = ExpenseCategory::first();

        $this->actingAs($staff)->post(route('expenses.store'), ['villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '500000',
            'receipts' => [UploadedFile::fake()->image('n.jpg')]]);
        $e = Expense::firstOrFail();

        $this->actingAs($owner)->get(route('expenses.show', $e))->assertForbidden(); // belum diperiksa
        $this->actingAs($manager)->get(route('reviews.index'))->assertOk()->assertSee($e->description);
        $this->actingAs($manager)->get(route('reviews.show', $e))->assertOk();

        $this->actingAs($manager)->post(route('reviews.return', $e), ['return_reason' => ''])->assertSessionHasErrors('return_reason');
        $this->actingAs($manager)->post(route('reviews.return', $e), ['return_reason' => 'Foto nota buram'])->assertRedirect();
        $e->refresh();
        $this->assertSame(ExpenseStatus::Returned, $e->status);
        Notification::assertSentTo($staff, ExpenseReturnedNotification::class);

        $this->actingAs($staff)->get(route('expenses.edit', $e))->assertOk()->assertSee('Foto nota buram')->assertSee('Kirim Ulang');

        // Staff memperbaiki → kembali Menunggu
        $this->actingAs($staff)->put(route('expenses.update', $e), ['villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '450000', 'description' => 'Klorin'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ExpenseStatus::Pending, $e->fresh()->status);

        $this->actingAs($manager)->post(route('reviews.approve', $e))->assertRedirect();
        $this->assertSame(ExpenseStatus::Valid, $e->fresh()->status);

        // Valid: staff tidak bisa ubah lagi, owner bisa lihat
        $this->actingAs($staff)->put(route('expenses.update', $e), ['villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '1'])->assertForbidden();
        $this->actingAs($owner)->get(route('expenses.show', $e))->assertOk();
        $this->actingAs($owner)->get(route('expenses.index'))->assertOk()->assertSee('Klorin');
        $this->actingAs($owner)->get(route('expenses.gallery', ['villa' => $villa->id]))->assertOk();
        $this->actingAs($owner)->get(route('expenses.create'))->assertForbidden();
    }

    public function test_bulk_valid_and_manager_expense_is_valid_immediately(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla();
        $cat = ExpenseCategory::first();
        $ids = collect(range(1, 3))->map(fn ($i) => Expense::create(['villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today(), 'description' => "N$i", 'amount' => 1000, 'status' => 'pending', 'created_by' => $staff->id])->id);

        $this->actingAs($manager)->post(route('reviews.bulk'), ['ids' => $ids->all()])->assertRedirect();
        $this->assertSame(3, Expense::where('status', 'valid')->count());

        $this->actingAs($manager)->post(route('expenses.store'), ['villa_id' => $villa->id, 'category_id' => $cat->id, 'expense_date' => today()->toDateString(), 'amount' => '200000', 'description' => 'Listrik'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ExpenseStatus::Valid, Expense::where('description', 'Listrik')->first()->status);
    }

    public function test_income_from_booking_links_and_confirms(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $b = Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->airbnb(), 'booking_ref' => 'HMZ', 'guest_name' => 'Ana', 'check_in' => '2026-10-01', 'check_out' => '2026-10-03', 'status' => 'needs_completion']);

        $this->actingAs($manager)->get(route('incomes.create', ['booking' => $b->id]))->assertOk();
        $this->actingAs($manager)->post(route('incomes.store'), ['booking_id' => $b->id, 'platform_id' => $this->airbnb(), 'payment_date' => '2026-10-04', 'gross_amount' => '5000000'])
            ->assertSessionHasNoErrors();

        $income = Income::firstOrFail();
        $this->assertSame($b->id, $income->booking_id);
        $this->assertSame('HMZ', $income->booking_ref);
        $this->assertSame('confirmed', $b->fresh()->status->value);
    }

    public function test_settings_categories(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $this->actingAs($manager)->get(route('settings.index'))->assertOk();
        $this->actingAs($manager)->post(route('settings.categories.store'), ['name' => 'Pest Control'])->assertSessionHasNoErrors();
        $this->assertSame(17, ExpenseCategory::count());
        $this->actingAs($manager)->post(route('settings.categories.store'), ['name' => 'Air'])->assertSessionHasErrors('name');
        $this->actingAs($this->makeUser(UserRole::Staff))->get(route('settings.index'))->assertForbidden();
    }
}
