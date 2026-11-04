<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ExpenseStatus;
use App\Enums\FeeBasis;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use App\Services\IncomeService;
use App\Services\VillaService;
use Illuminate\Database\Seeder;

/**
 * DATA CONTOH sederhana untuk mencoba aplikasi — JANGAN dijalankan di produksi.
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 *
 * Login (username / kata sandi password123):
 *   master   — manager utama, melihat SEMUA wilayah & villa
 *   manager  — Manager wilayah Ubud   (Villa Contoh)
 *   manager2 — Manager wilayah Canggu (Villa Contoh 2)
 *   owner    — pemilik kedua villa
 *   staff    — staff Villa Contoh
 *
 * Isi per villa: 1 reservasi, 1 pemasukan; Villa Contoh juga 1 pengeluaran (menunggu diperiksa).
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder tidak boleh dijalankan di produksi.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        // Akun Master dari ManagerSeeder dibuat sederhana untuk demo
        $master = User::role(UserRole::Master->value)->firstOrFail();
        $master->forceFill(['name' => 'Master', 'username' => 'master', 'email' => 'master@propertyme.test', 'password' => self::PASSWORD])->save();

        $manager = $this->user('manager', 'Manager Ubud', UserRole::Manager);
        $manager2 = $this->user('manager2', 'Manager Canggu', UserRole::Manager);
        $owner = $this->user('owner', 'Owner Contoh', UserRole::Owner);
        $staff = $this->user('staff', 'Staff Contoh', UserRole::Staff);

        if (Villa::exists()) {
            $this->command?->info('Data contoh sudah ada — dilewati.');

            return;
        }

        $ubud = Region::create(['name' => 'Ubud', 'description' => 'Data contoh']);
        $canggu = Region::create(['name' => 'Canggu', 'description' => 'Data contoh']);
        $ubud->managers()->attach($manager);
        $canggu->managers()->attach($manager2);

        $villaService = app(VillaService::class);
        $base = [
            'owner_id' => $owner->id, 'fee_percentage' => 15, 'fee_basis' => FeeBasis::NetOfPlatform->value,
            'started_at' => today()->startOfYear()->toDateString(), 'fee_effective_from' => today()->startOfYear()->toDateString(),
            'timezone' => 'Asia/Makassar', 'is_active' => true, 'notes' => 'Data contoh — boleh dihapus.',
        ];
        $villa = $villaService->create($base + ['name' => 'Villa Contoh', 'location' => 'Ubud, Gianyar', 'region_id' => $ubud->id, 'staff_ids' => [$staff->id]], $master);
        $villa2 = $villaService->create($base + ['name' => 'Villa Contoh 2', 'location' => 'Canggu, Badung', 'region_id' => $canggu->id, 'staff_ids' => []], $master);

        $airbnb = BookingPlatform::where('slug', 'airbnb')->firstOrFail();
        $bookingCom = BookingPlatform::where('slug', 'booking-com')->firstOrFail();

        // Villa Contoh (Ubud): reservasi berlangsung + pemasukan + nota menunggu diperiksa
        $this->bookingWithIncome($villa, $airbnb, 'HMCONTOH01', 'Emma Wilson', 10_000_000, 300_000, $master);
        Expense::create([
            'villa_id' => $villa->id, 'category_id' => ExpenseCategory::where('name', 'Listrik')->value('id') ?? ExpenseCategory::value('id'),
            'expense_date' => today(), 'description' => 'Token listrik PLN', 'amount' => 1_000_000,
            'payment_method' => 'cash', 'status' => ExpenseStatus::Pending, 'created_by' => $staff->id,
        ]);

        // Villa Contoh 2 (Canggu): reservasi + pemasukan
        $this->bookingWithIncome($villa2, $bookingCom, '4012345678', 'Kenji Tanaka', 8_000_000, 1_200_000, $master);

        $this->command?->info('Data contoh dibuat. Login: master / manager / manager2 / owner / staff — kata sandi: '.self::PASSWORD);
    }

    private function bookingWithIncome(Villa $villa, BookingPlatform $platform, string $ref, string $guest, int $gross, int $commission, User $actor): void
    {
        $booking = Booking::create([
            'villa_id' => $villa->id, 'platform_id' => $platform->id, 'source' => 'manual',
            'booking_ref' => $ref, 'guest_name' => $guest, 'guest_count' => 2,
            'check_in' => today()->subDay(), 'check_out' => today()->addDays(3), 'arrival_time' => '14:00',
            'status' => BookingStatus::CheckedIn, 'notes' => 'Data contoh', 'created_by' => $actor->id,
        ]);

        app(IncomeService::class)->create([
            'booking_id' => $booking->id, 'villa_id' => $villa->id, 'platform_id' => $platform->id,
            'booking_ref' => $ref, 'payment_date' => today()->toDateString(),
            'gross_amount' => $gross, 'platform_fee' => $commission, 'other_deduction' => 0,
            'is_net_of_platform' => false, 'notes' => 'Data contoh',
        ], $actor);
    }

    private function user(string $username, string $name, UserRole $role): User
    {
        $user = User::firstOrCreate(['username' => $username], [
            'name' => $name, 'email' => $username.'@propertyme.test', 'password' => self::PASSWORD, 'is_active' => true,
        ]);
        $user->syncRoles([$role->value]);

        return $user;
    }
}
