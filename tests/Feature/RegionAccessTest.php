<?php

namespace Tests\Feature;

use App\Enums\FeeBasis;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\BookingPlatform;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use App\Notifications\ExpenseSubmittedNotification;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Master melihat semua wilayah; Manager wilayah hanya villa di wilayahnya.
 * Ubud: Villa A (Manager A). Canggu: Villa B (Manager B).
 */
class RegionAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $master;
    private User $managerA;
    private User $managerB;
    private Villa $villaA;
    private Villa $villaB;
    private Region $ubud;
    private Region $canggu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();

        $this->master = $this->makeUser(UserRole::Master);
        $this->managerA = $this->makeUser(UserRole::Manager);
        $this->managerB = $this->makeUser(UserRole::Manager);

        $this->ubud = Region::create(['name' => 'Ubud']);
        $this->canggu = Region::create(['name' => 'Canggu']);
        $this->ubud->managers()->attach($this->managerA);
        $this->canggu->managers()->attach($this->managerB);

        $this->villaA = $this->makeVilla(['name' => 'Villa Ubud A', 'region_id' => $this->ubud->id]);
        $this->villaB = $this->makeVilla(['name' => 'Villa Canggu B', 'region_id' => $this->canggu->id]);
        foreach ([$this->villaA, $this->villaB] as $v) {
            $v->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform, 'effective_from' => '2026-01-01']);
        }
    }

    private function income(Villa $villa, string $ref, string $gross = '10000000'): Income
    {
        $this->actingAs($this->master)->post(route('incomes.store'), [
            'villa_id' => $villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'payment_date' => '2026-10-10', 'gross_amount' => $gross, 'platform_fee' => '0', 'booking_ref' => $ref,
        ])->assertSessionHasNoErrors();

        return Income::where('booking_ref', $ref)->firstOrFail();
    }

    public function test_manager_only_sees_villas_in_own_region(): void
    {
        $this->assertSame([$this->villaA->id], $this->managerA->accessibleVillaIds());
        $this->assertNull($this->master->accessibleVillaIds());

        $this->actingAs($this->managerA)->get(route('villas.index'))->assertOk()
            ->assertSee('Villa Ubud A')->assertDontSee('Villa Canggu B');
        $this->actingAs($this->managerA)->get(route('villas.show', $this->villaA))->assertOk();
        $this->actingAs($this->managerA)->get(route('villas.show', $this->villaB))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('villas.edit', $this->villaB))->assertForbidden();

        $this->actingAs($this->master)->get(route('villas.index'))->assertOk()
            ->assertSee('Villa Ubud A')->assertSee('Villa Canggu B');
    }

    public function test_manager_sees_net_profit_of_own_villas_only_and_master_sees_all(): void
    {
        $incA = $this->income($this->villaA, 'HMA', '10000000');
        $incB = $this->income($this->villaB, 'HMB', '50000000');

        // Laba: Manager A hanya dari villa A; Master dari keduanya
        [$from, $to] = [\Illuminate\Support\Carbon::parse('2026-10-01'), \Illuminate\Support\Carbon::parse('2026-10-31')];
        $sA = app(FinanceCalculator::class)->summary(FinanceFilter::for($this->managerA, $from, $to));
        $this->assertSame('10000000.00', $sA->gross);
        $sM = app(FinanceCalculator::class)->summary(FinanceFilter::for($this->master, $from, $to));
        $this->assertSame('60000000.00', $sM->gross);

        $this->actingAs($this->managerA)->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertOk()
            ->assertSee('Rp 10.000.000')->assertDontSee('Rp 60.000.000');
        $this->actingAs($this->managerA)->get(route('dashboard', ['year' => 2026, 'month' => 10, 'villa' => $this->villaB->id]))->assertForbidden();
        $this->actingAs($this->master)->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertOk()->assertSee('Rp 60.000.000');

        // Transaksi & laporan villa lain tertutup
        $this->actingAs($this->managerA)->get(route('incomes.show', $incA))->assertOk();
        $this->actingAs($this->managerA)->get(route('incomes.show', $incB))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('incomes.edit', $incB))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('incomes.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()
            ->assertSee('HMA')->assertDontSee('HMB');
        $this->actingAs($this->managerA)->get(route('reports.monthly', ['villa' => $this->villaB->id, 'year' => 2026, 'month' => 10]))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('search', ['q' => 'HMB']))->assertOk()->assertDontSee('Rp 50.000.000');

        // Tidak bisa mencatat transaksi untuk villa lain
        $this->actingAs($this->managerA)->post(route('incomes.store'), [
            'villa_id' => $this->villaB->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'payment_date' => '2026-10-11', 'gross_amount' => '1000000', 'platform_fee' => '0', 'booking_ref' => 'HACK',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post(route('bookings.store'), [
            'villa_id' => $this->villaB->id, 'platform_id' => BookingPlatform::where('slug', 'direct')->value('id'),
            'check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'status' => 'confirmed',
        ])->assertForbidden();
    }

    public function test_expense_review_and_notifications_follow_region(): void
    {
        Notification::fake();
        $staffB = $this->makeUser(UserRole::Staff);
        $staffB->assignedVillas()->attach($this->villaB);

        $this->actingAs($staffB)->post(route('expenses.store'), [
            'villa_id' => $this->villaB->id, 'category_id' => ExpenseCategory::first()->id, 'expense_date' => today()->toDateString(),
            'amount' => '250000', 'receipts' => [UploadedFile::fake()->image('n.jpg')],
        ])->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();

        Notification::assertSentTo([$this->master, $this->managerB], ExpenseSubmittedNotification::class);
        Notification::assertNotSentTo($this->managerA, ExpenseSubmittedNotification::class);

        $this->actingAs($this->managerA)->get(route('reviews.index'))->assertOk()->assertDontSee('Villa Canggu B');
        $this->actingAs($this->managerA)->get(route('reviews.show', $expense))->assertForbidden();
        $this->actingAs($this->managerA)->post(route('reviews.approve', $expense))->assertForbidden();
        $this->actingAs($this->managerB)->post(route('reviews.approve', $expense))->assertRedirect();
        $this->assertSame('valid', $expense->fresh()->status->value);
    }

    public function test_master_only_actions(): void
    {
        // Tambah villa, wilayah, pengaturan: khusus Master
        $this->actingAs($this->managerA)->get(route('villas.create'))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('regions.index'))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->managerA)->patch(route('villas.toggle', $this->villaA))->assertForbidden();
        $this->actingAs($this->master)->get(route('regions.index'))->assertOk()->assertSee('Ubud')->assertSee('Canggu');

        // Manager boleh edit info villanya, tapi % fee, owner & wilayah tidak berubah
        $this->actingAs($this->managerA)->put(route('villas.update', $this->villaA), [
            'name' => 'Villa Ubud A (baru)', 'fee_percentage' => '50', 'fee_basis' => FeeBasis::Gross->value,
            'region_id' => $this->canggu->id, 'timezone' => 'Asia/Makassar', 'is_active' => '1',
        ])->assertRedirect();
        $this->villaA->refresh();
        $this->assertSame('Villa Ubud A (baru)', $this->villaA->name);
        $this->assertSame('15.00', (string) $this->villaA->fee_percentage);
        $this->assertSame($this->ubud->id, $this->villaA->region_id);

        // Final: Manager boleh untuk villanya; buka kunci hanya Master
        $report = MonthlyReport::create(['villa_id' => $this->villaA->id, 'year' => 2026, 'month' => 9, 'status' => ReportStatus::Final]);
        $this->actingAs($this->managerA)->post(route('reports.unlock', $report), ['unlock_reason' => 'Koreksi data'])->assertForbidden();
        $this->actingAs($this->master)->post(route('reports.unlock', $report), ['unlock_reason' => 'Koreksi data'])->assertRedirect();
        $this->assertSame(ReportStatus::Draft, $report->fresh()->status);
    }

    public function test_manager_manages_only_staff_in_own_region(): void
    {
        $staffA = $this->makeUser(UserRole::Staff);
        $staffA->assignedVillas()->attach($this->villaA);
        $staffB = $this->makeUser(UserRole::Staff);
        $staffB->assignedVillas()->attach($this->villaB);
        $owner = $this->makeUser(UserRole::Owner);

        $this->actingAs($this->managerA)->get(route('users.index'))->assertOk()
            ->assertSee($staffA->name)->assertDontSee($staffB->name)->assertDontSee($owner->name)->assertDontSee($this->managerB->name);
        $this->actingAs($this->managerA)->get(route('users.edit', $staffB))->assertForbidden();
        $this->actingAs($this->managerA)->get(route('users.edit', $owner))->assertForbidden();

        $base = ['name' => 'Staff Baru', 'email' => 'baru@test.id', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'is_active' => '1'];
        // Tidak boleh membuat Owner/Manager atau menugaskan ke villa wilayah lain
        $this->actingAs($this->managerA)->post(route('users.store'), $base + ['role' => 'owner'])->assertSessionHasErrors('role');
        $this->actingAs($this->managerA)->post(route('users.store'), $base + ['role' => 'staff', 'villa_ids' => [$this->villaB->id]])->assertSessionHasErrors('villa_ids');
        $this->actingAs($this->managerA)->post(route('users.store'), $base + ['role' => 'staff', 'villa_ids' => [$this->villaA->id]])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'baru@test.id')->firstOrFail()->canAccessVilla($this->villaA));
    }

    public function test_master_assigns_region_and_new_villas_follow_automatically(): void
    {
        $managerC = $this->makeUser(UserRole::Manager);
        $this->assertSame([], $managerC->accessibleVillaIds());

        // Master memberi wilayah Canggu ke Manager C lewat form user
        $this->actingAs($this->master)->put(route('users.update', $managerC), [
            'name' => $managerC->name, 'email' => $managerC->email, 'role' => 'manager', 'is_active' => '1', 'region_ids' => [$this->canggu->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$this->villaB->id], $managerC->fresh()->accessibleVillaIds());

        // Villa baru di Canggu otomatis bisa diakses Manager B & C
        $new = $this->makeVilla(['name' => 'Villa Canggu Baru', 'region_id' => $this->canggu->id]);
        $this->assertTrue($this->managerB->fresh()->canAccessVilla($new));
        $this->assertFalse($this->managerA->fresh()->canAccessVilla($new));

        // Form wilayah: pindahkan villa A ke Canggu
        $this->actingAs($this->master)->put(route('regions.update', $this->canggu), [
            'name' => 'Canggu', 'villa_ids' => [$this->villaB->id, $new->id, $this->villaA->id], 'manager_ids' => [$this->managerB->id],
        ])->assertRedirect(route('regions.index'));
        $this->assertSame($this->canggu->id, $this->villaA->fresh()->region_id);
        $this->assertFalse($this->managerA->fresh()->canAccessVilla($this->villaA));
        $this->assertFalse($managerC->fresh()->canAccessVilla($this->villaB), 'Manager C dilepas dari Canggu');
    }
}
