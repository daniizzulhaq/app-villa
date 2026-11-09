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
use App\Models\User;
use App\Models\Villa;
use App\Notifications\ReportFinalizedNotification;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $owner;
    private Villa $villa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->manager = $this->makeUser(UserRole::Master);
        $this->owner = $this->makeUser(UserRole::Owner);
        $this->villa = $this->makeVilla(['owner_id' => $this->owner->id]);
        $this->villa->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform, 'effective_from' => '2026-01-01']);

        // Contoh spesifikasi: Gross 80 jt, komisi 3 jt, fee 15% net, pengeluaran 12 jt (Oktober 2026)
        $this->actingAs($this->manager)->post(route('incomes.store'), [
            'villa_id' => $this->villa->id, 'platform_id' => BookingPlatform::where('slug', 'airbnb')->value('id'),
            'payment_date' => '2026-10-20', 'gross_amount' => '80000000', 'platform_fee' => '3000000', 'booking_ref' => 'HMX',
        ])->assertSessionHasNoErrors();
        Expense::create(['villa_id' => $this->villa->id, 'category_id' => ExpenseCategory::first()->id, 'expense_date' => '2026-10-05',
            'description' => 'Gaji', 'amount' => '12000000', 'status' => 'valid', 'created_by' => $this->manager->id]);
        // Pending tidak dihitung
        Expense::create(['villa_id' => $this->villa->id, 'category_id' => ExpenseCategory::first()->id, 'expense_date' => '2026-10-06',
            'description' => 'Belum diperiksa', 'amount' => '999999', 'status' => 'pending', 'created_by' => $this->manager->id]);
    }

    public function test_calculator_from_database_matches_spec_example(): void
    {
        $s = app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10));

        $this->assertSame('80000000.00', $s->gross);
        $this->assertSame('77000000.00', $s->netRevenue);
        $this->assertSame('11550000.00', $s->managementFee);
        $this->assertSame('65450000.00', $s->revenueAfterFee);
        $this->assertSame('12000000.00', $s->expenses, 'Hanya pengeluaran Valid');
        $this->assertSame('53450000.00', $s->netProfit);
        $this->assertSame('66,8%', $s->marginLabel());

        // Edit pemasukan langsung memperbarui total (tanpa data dobel)
        Income::first()->delete();
        $this->assertSame('-12000000.00', app(FinanceCalculator::class)->summary(FinanceFilter::month(2026, 10))->netProfit);
    }

    public function test_dashboard_shows_numbers_and_filters(): void
    {
        $this->actingAs($this->manager)->get(route('dashboard', ['year' => 2026, 'month' => 10]))
            ->assertOk()->assertSee('Rp 53.450.000')->assertSee('66,8%');

        $this->actingAs($this->manager)->get(route('dashboard', ['year' => 2026, 'month' => 10, 'platform' => BookingPlatform::where('slug', 'direct')->value('id')]))
            ->assertOk()->assertSee('−Rp 12.000.000');

        $this->actingAs($this->manager)->get(route('dashboard', ['year' => 2026, 'month' => 9]))->assertOk()->assertSee('N/A');
        $this->actingAs($this->owner)->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertOk()->assertSee('Rp 53.450.000');

        $other = $this->makeVilla();
        $this->actingAs($this->owner)->get(route('dashboard', ['villa' => $other->id]))->assertForbidden();
    }

    public function test_finalize_locks_month_notifies_owner_and_unlock_is_audited(): void
    {
        Notification::fake();

        // Masih ada nota pending → diblokir
        $this->actingAs($this->manager)->post(route('reports.finalize'), ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10])
            ->assertSessionHasErrors('report');
        Expense::where('status', 'pending')->update(['status' => 'valid']);

        $this->actingAs($this->manager)->post(route('reports.finalize'), ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10])->assertSessionHasNoErrors();
        $report = MonthlyReport::firstOrFail();
        $this->assertSame(ReportStatus::Final, $report->status);
        Notification::assertSentTo($this->owner, ReportFinalizedNotification::class);

        // Terkunci
        $income = Income::first();
        $this->actingAs($this->manager)->get(route('incomes.edit', $income))->assertForbidden();

        $this->actingAs($this->manager)->post(route('reports.unlock', $report), ['unlock_reason' => ''])->assertSessionHasErrors('unlock_reason');
        $this->actingAs($this->manager)->post(route('reports.unlock', $report), ['unlock_reason' => 'Koreksi nominal komisi'])->assertRedirect();
        $this->assertSame(ReportStatus::Draft, $report->fresh()->status);
        $this->actingAs($this->manager)->get(route('incomes.edit', $income))->assertOk();

        $this->assertTrue(Audit::where('auditable_type', MonthlyReport::class)->where('event', 'updated')->exists());
        $this->actingAs($this->manager)->get(route('audits.index'))->assertOk()->assertSee('Koreksi nominal komisi');
    }

    public function test_owner_sees_only_final_reports_and_staff_none(): void
    {
        $this->actingAs($this->owner)->get(route('reports.monthly', ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10]))
            ->assertOk()->assertSee('Laporan belum Final')->assertDontSee('Rp 53.450.000');

        MonthlyReport::create(['villa_id' => $this->villa->id, 'year' => 2026, 'month' => 10, 'status' => ReportStatus::Final]);

        $this->actingAs($this->owner)->get(route('reports.monthly', ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10]))
            ->assertOk()->assertSee('Rp 53.450.000')->assertSee('15% dari pendapatan setelah komisi platform');
        $this->actingAs($this->owner)->get(route('reports.annual', ['year' => 2026]))->assertOk()->assertSee('Rp 53.450.000');
        $this->actingAs($this->owner)->get(route('reports.index'))->assertOk();

        $staff = $this->makeUser(UserRole::Staff);
        $staff->assignedVillas()->attach($this->villa);
        $this->actingAs($staff)->get(route('reports.monthly'))->assertForbidden();
        $this->actingAs($staff)->get(route('reports.pdf'))->assertForbidden();
        $this->actingAs($staff)->get(route('analytics.index'))->assertForbidden();
    }

    public function test_pdf_and_excel_exports(): void
    {
        $params = ['villa' => $this->villa->id, 'year' => 2026, 'month' => 10];

        $pdf = $this->actingAs($this->manager)->get(route('reports.pdf', $params))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('laporan-', $pdf->headers->get('content-disposition'));

        $this->actingAs($this->manager)->get(route('reports.excel', $params))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->manager)->get(route('reports.pdf', ['type' => 'annual', 'year' => 2026]))->assertOk();
    }

    public function test_analytics_page_and_presets(): void
    {
        foreach (['this_month', 'last_12', 'this_year', 'last_year'] as $preset) {
            $this->actingAs($this->manager)->get(route('analytics.index', ['preset' => $preset, 'granularity' => 'quarter']))->assertOk();
        }
        $this->actingAs($this->manager)->get(route('analytics.index', ['preset' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee('Rp 53.450.000');
        $this->actingAs($this->owner)->get(route('analytics.index'))->assertOk();
    }

    public function test_import_preview_flags_duplicates_and_errors_then_imports(): void
    {
        $name = $this->villa->name;
        $csv = "villa,platform,kode_booking,tanggal_bayar,gross,komisi,potongan_lain\n"
            ."{$name},Airbnb,HMX,20/10/2026,\"Rp 1.000.000\",100000,0\n"        // duplikat (sudah ada)
            ."{$name},Airbnb,HMNEW,21/10/2026,2.500.000,375000,0\n"            // ok
            ."{$name},Airbnb,HMNEW,21/10/2026,2500000,375000,0\n"              // dobel di file
            ."Villa Tidak Ada,Airbnb,HM9,21/10/2026,100000,0,0\n"              // error villa
            ."{$name},Booking.com,BK1,,500000,0,0\n";                          // error tanggal
        $file = UploadedFile::fake()->createWithContent('payout.csv', $csv);

        $res = $this->actingAs($this->manager)->post(route('imports.preview'), ['file' => $file, 'date_format' => 'd/m/Y'])->assertOk();
        $preview = $res->viewData('preview');
        $this->assertEquals(['ok' => 1, 'duplicate' => 2, 'error' => 2], array_intersect_key($preview['counts'], ['ok' => 0, 'duplicate' => 0, 'error' => 0]));
        $res->assertSee('Sudah tercatat sebelumnya')->assertSee('Dobel di file');

        $this->actingAs($this->manager)->post(route('imports.store'), ['token' => $res->viewData('token')])->assertRedirect(route('imports.create'));
        $new = Income::where('booking_ref', 'HMNEW')->firstOrFail();
        $this->assertSame('2500000.00', (string) $new->gross_amount);
        $this->assertSame('318750.00', (string) $new->fee_amount); // 15% × (2.500.000 − 375.000)
        $this->assertSame(2, Income::count());

        $this->actingAs($this->manager)->get(route('imports.template'))->assertOk();
        $this->actingAs($this->owner)->get(route('imports.create'))->assertForbidden();
    }

    public function test_import_fills_reservation_price_by_code_and_by_dates(): void
    {
        $airbnb = BookingPlatform::where('slug', 'airbnb')->value('id');
        $bcom = BookingPlatform::where('slug', 'booking-com')->value('id') ?? BookingPlatform::where('slug', '!=', 'airbnb')->value('id');
        $bcomName = BookingPlatform::find($bcom)->name;
        $mk = fn ($attrs) => \App\Models\Booking::create($attrs + ['villa_id' => $this->villa->id, 'source' => 'ical', 'status' => 'needs_completion']);

        $hm = $mk(['platform_id' => $airbnb, 'ical_uid' => 'a', 'booking_ref' => 'HMPRICE1', 'check_in' => '2026-11-10', 'check_out' => '2026-11-13']);
        $bk = $mk(['platform_id' => $bcom, 'ical_uid' => 'b', 'check_in' => '2026-11-20', 'check_out' => '2026-11-22']);
        $manual = $mk(['platform_id' => $airbnb, 'ical_uid' => 'c', 'booking_ref' => 'HMMANUAL', 'check_in' => '2026-11-25', 'check_out' => '2026-11-27',
            'total_price' => 1000000, 'price_source' => 'manual']);

        // Export Airbnb: kode booking + tanggal bayar
        $csv = "Date,Type,Confirmation Code,Start Date,Nights,Guest,Listing,Amount,Host Fee,Gross Earnings\n"
            ."11/14/2026,Reservation,HMPRICE1,11/10/2026,3,John Smith,{$this->villa->name},5100000,900000,6000000\n"
            ."11/28/2026,Reservation,HMMANUAL,11/25/2026,2,Ann,{$this->villa->name},1700000,300000,2000000\n";
        $res = $this->actingAs($this->manager)->post(route('imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('airbnb.csv', $csv), 'platform_id' => $airbnb, 'date_format' => 'm/d/Y',
        ])->assertOk()->assertSee('harga diisi');
        $this->assertSame(1, $res->viewData('preview')['prices'], 'Harga manual tidak ikut dihitung');
        $this->actingAs($this->manager)->post(route('imports.store'), ['token' => $res->viewData('token')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 harga reservasi diperbarui'));

        $hm->refresh();
        $this->assertSame('6000000.00', $hm->total_price);
        $this->assertSame('import', $hm->price_source);
        $this->assertSame('John Smith', $hm->guest_name);
        $this->assertSame($hm->id, Income::where('booking_ref', 'HMPRICE1')->value('booking_id'));
        $this->assertSame('1000000.00', $manual->fresh()->total_price, 'Harga manual tidak ditimpa');

        // Export reservasi Booking.com: tanpa kode di iCal → dicocokkan lewat tanggal; tanpa tanggal bayar → pakai check-out
        $csv = "Book number,Booked by,Guest name(s),Check-in,Check-out,Status,Price,Commission amount\n"
            ."4012345678,Ayu,Ayu Lestari,20/11/2026,22/11/2026,ok,3.500.000 IDR,525.000 IDR\n";
        $res = $this->actingAs($this->manager)->post(route('imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('bcom.csv', $csv), 'villa_id' => $this->villa->id, 'platform_id' => $bcom, 'date_format' => 'd/m/Y',
        ])->assertOk();
        $this->assertSame('ok', $res->viewData('preview')['rows'][0]['status'], $bcomName.': '.implode(';', $res->viewData('preview')['rows'][0]['errors']));
        $this->actingAs($this->manager)->post(route('imports.store'), ['token' => $res->viewData('token')]);

        $bk->refresh();
        $this->assertSame('3500000.00', $bk->total_price);
        $this->assertSame('4012345678', $bk->booking_ref);
        $this->assertSame('Ayu Lestari', $bk->guest_name);
        $income = Income::where('booking_ref', '4012345678')->firstOrFail();
        $this->assertSame($bk->id, $income->booking_id);
        $this->assertSame('2026-11-22', $income->payment_date->toDateString());
    }

    public function test_global_search_respects_roles(): void
    {
        $this->actingAs($this->manager)->get(route('search', ['q' => 'HMX']))->assertOk()->assertSee('Rp 80.000.000');
        $this->actingAs($this->manager)->get(route('search', ['q' => 'Oktober 2026']))->assertOk()->assertSee('Oktober 2026');
        $this->actingAs($this->manager)->get(route('search', ['q' => 'Gaji']))->assertOk()->assertSee('Gaji Staff');

        $staff = $this->makeUser(UserRole::Staff);
        $staff->assignedVillas()->attach($this->villa);
        $this->actingAs($staff)->get(route('search', ['q' => 'HMX']))->assertOk()->assertDontSee('Rp 80.000.000');
    }

    public function test_notifications_center(): void
    {
        $this->owner->notify(new ReportFinalizedNotification(MonthlyReport::create(['villa_id' => $this->villa->id, 'year' => 2026, 'month' => 10, 'status' => ReportStatus::Final])));
        $n = $this->owner->notifications()->first();

        $this->actingAs($this->owner)->get(route('notifications.index'))->assertOk()->assertSee('sudah Final');
        $this->actingAs($this->owner)->get(route('notifications.open', $n->id))->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);
        $this->actingAs($this->manager)->get(route('notifications.open', $n->id))->assertNotFound();
    }
}
