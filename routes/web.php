<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IcalExportController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Manager\AuditController;
use App\Http\Controllers\Manager\ImportController;
use App\Http\Controllers\Manager\RegionController;
use App\Http\Controllers\Manager\ReviewController;
use App\Http\Controllers\Manager\SettingsController;
use App\Http\Controllers\Manager\SyncController;
use App\Http\Controllers\Manager\UserController;
use App\Http\Controllers\Manager\VillaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Staff\ActivityController;
use App\Http\Controllers\Staff\CalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publik
|--------------------------------------------------------------------------
*/
// Feed iCal per villa (token rahasia) untuk diimpor Airbnb / Booking.com
Route::get('/ical/{token}.ics', IcalExportController::class)->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:60,1')->name('ical.export');

// Ganti bahasa (bisa dari halaman login maupun setelah masuk)
Route::post('/bahasa', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Semua pengguna yang login & aktif (akses detail dicek Policy)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => redirect(auth()->user()->homeUrl()))->name('home');
    Route::get('/dashboard', DashboardController::class)->name('dashboard'); // Staff diarahkan ke Kalender

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/kata-sandi', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('/cari', SearchController::class)->name('search');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifikasi/{id}', [NotificationController::class, 'open'])->name('notifications.open');

    // File privat (nota & bukti bayar) — dicek Policy
    Route::get('/files/nota/{receipt}', [PrivateFileController::class, 'receipt'])->name('files.receipt');
    Route::get('/files/bukti-bayar/{attachment}', [PrivateFileController::class, 'incomeAttachment'])->name('files.income');

    // Kalender (semua role; tanpa nominal)
    Route::get('/bookings/events', [BookingController::class, 'events'])->name('bookings.events');

    // Pengeluaran: Staff (miliknya), Manager (semua), Owner (lihat Valid)
    Route::get('/expenses/galeri', [ExpenseController::class, 'gallery'])->name('expenses.gallery');
    Route::resource('expenses', ExpenseController::class);

    /*
    |----------------------------------------------------------------------
    | Staff
    |----------------------------------------------------------------------
    */
    Route::middleware('role:staff')->group(function () {
        Route::get('/kalender', CalendarController::class)->name('staff.calendar');
        Route::get('/riwayat', ActivityController::class)->name('staff.activity');
    });

    /*
    |----------------------------------------------------------------------
    | Manager — kelola (didaftarkan sebelum route {id} agar /create tidak tertangkap)
    |----------------------------------------------------------------------
    */
    Route::middleware('role:master|manager')->group(function () {
        Route::patch('/villas/{villa}/status', [VillaController::class, 'toggleActive'])->name('villas.toggle');
        Route::patch('/villas/{villa}/ical/disable', [VillaController::class, 'disableIcal'])->name('villas.disable-ical');
        Route::resource('villas', VillaController::class);

        Route::patch('/users/{user}/status', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::resource('users', UserController::class)->except('show');

        Route::resource('bookings', BookingController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::get('/sinkronisasi', [SyncController::class, 'index'])->name('sync.index');
        Route::post('/sinkronisasi', [SyncController::class, 'run'])->middleware('throttle:10,1')->name('sync.run');

        Route::resource('incomes', IncomeController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::get('/import', [ImportController::class, 'create'])->name('imports.create');
        Route::post('/import/pratinjau', [ImportController::class, 'preview'])->name('imports.preview');
        Route::post('/import', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/import/template', [ImportController::class, 'template'])->name('imports.template');

        Route::get('/pemeriksaan', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/pemeriksaan/massal', [ReviewController::class, 'bulk'])->name('reviews.bulk');
        Route::get('/pemeriksaan/{expense}', [ReviewController::class, 'show'])->name('reviews.show');
        Route::post('/pemeriksaan/{expense}/valid', [ReviewController::class, 'approve'])->name('reviews.approve');
        Route::post('/pemeriksaan/{expense}/kembalikan', [ReviewController::class, 'return'])->name('reviews.return');

        Route::post('/laporan/final', [ReportController::class, 'finalize'])->name('reports.finalize');
        Route::post('/laporan/{report}/buka-kunci', [ReportController::class, 'unlock'])->name('reports.unlock');

        Route::get('/audit-log', AuditController::class)->name('audits.index'); // Manager: hanya villa wilayahnya
    });

    /*
    |----------------------------------------------------------------------
    | Master (manager utama) — wilayah & pengaturan sistem
    |----------------------------------------------------------------------
    */
    Route::middleware('role:master')->group(function () {
        Route::resource('regions', RegionController::class)->except('show');

        Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/pengaturan/kategori', [SettingsController::class, 'storeCategory'])->name('settings.categories.store');
        Route::patch('/pengaturan/kategori/{category}', [SettingsController::class, 'updateCategory'])->name('settings.categories.update');
        Route::delete('/pengaturan/kategori/{category}', [SettingsController::class, 'destroyCategory'])->name('settings.categories.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Manager & Owner — lihat
    |----------------------------------------------------------------------
    */
    Route::middleware('role:master|manager|owner')->group(function () {
        Route::get('/bookings/kalender', [BookingController::class, 'calendar'])->name('bookings.calendar');
        Route::resource('bookings', BookingController::class)->only(['index', 'show']);

        Route::resource('incomes', IncomeController::class)->only(['index', 'show']);

        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/bulanan', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('/laporan/tahunan', [ReportController::class, 'annual'])->name('reports.annual');
        Route::get('/laporan/pdf', [ReportController::class, 'pdf'])->middleware('throttle:20,1')->name('reports.pdf');
        Route::get('/laporan/excel', [ReportController::class, 'excel'])->middleware('throttle:20,1')->name('reports.excel');

        Route::get('/analitik', AnalyticsController::class)->name('analytics.index');
    });
});
