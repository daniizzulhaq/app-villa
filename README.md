# PropertyMe – Villa Financial Management System

Aplikasi web untuk mencatat, menghitung, dan memantau keuangan banyak villa di Bali:
reservasi (iCal Airbnb/Booking.com + manual), pemasukan dengan management fee otomatis,
nota pengeluaran dari HP Staff, pemeriksaan nota, laporan bulanan/tahunan (PDF & Excel), dan analitik.

**Stack:** Laravel 12 · PHP 8.2+ · MySQL · Blade + Bootstrap 5 + Alpine.js · FullCalendar · ApexCharts ·
spatie/laravel-permission · owen-it/laravel-auditing · maatwebsite/excel · barryvdh/laravel-dompdf ·
brick/money · intervention/image · johngrogg/ics-parser · spatie/icalendar-generator

## Menjalankan lokal

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # isi DB_*
php artisan migrate --seed          # data dasar + akun Manager (kata sandi ditampilkan sekali)
php artisan db:seed --class=DemoSeeder   # opsional: data contoh 2 wilayah, login master / manager / manager2 / owner / staff, sandi password123
php artisan storage:link
php artisan serve
```

Test: buat database `propertyme_test`, lalu `php artisan test`.

## Rumus (satu sumber: `app/Services/Finance/FinanceCalculator.php`)

```
Net Booking Revenue = Gross − Komisi Platform − Potongan Lain
Management Fee      = % Fee × Basis Fee (gross | setelah komisi | custom)
Revenue After Fee   = Net Booking Revenue − Management Fee
Final Net Profit    = Revenue After Fee − Total Pengeluaran Valid
Net Profit Margin   = Final Net Profit ÷ Gross × 100%   (Gross 0 → N/A)
```

## Role & wilayah

| Role | Akses |
|---|---|
| **Master** (manager utama) | Semua wilayah & villa, termasuk laba bersih. Satu-satunya yang bisa: tambah/hapus villa, ubah % fee, atur owner & wilayah, buat akun Manager/Owner, buka kunci laporan Final, pengaturan. |
| **Manager** (wilayah) | Hanya villa di wilayah yang ditugaskan (menu **Wilayah**): reservasi, pemasukan, pengeluaran, pemeriksaan nota, laporan & laba bersih villanya, tandai Final, akun Staff di villanya. |
| **Owner** | Villa miliknya, laporan yang sudah Final. |
| **Staff** | Kalender & upload nota untuk villa yang ditugaskan. |

Villa baru yang dimasukkan ke suatu wilayah otomatis bisa diakses Manager wilayah itu.
Akun pertama dari `ManagerSeeder` adalah **Master**.

## Bahasa (9 bahasa)

English, Français, Nederlands, Deutsch, Bahasa Indonesia, Русский, 中文, 한국어, 日本語.
Pemilih bahasa ada di halaman login, topbar (ikon terjemahan), dan menu HP. Pilihan disimpan per akun
(`users.locale`) dan dipakai juga untuk notifikasi email.

- Teks aplikasi ditulis dalam Bahasa Indonesia sebagai kunci: `__('Simpan')`.
- Terjemahan ada di `lang/{en,fr,nl,de,ru,zh_CN,ko,ja}.json` (kunci Indonesia → teks terjemahan).
  `lang/id.json` berisi kunci yang sama (identik) agar Bahasa Indonesia tidak jatuh ke bahasa Inggris.
- Teks yang dibuat JavaScript memakai `t('...')`; kamusnya dikirim dari `resources/views/layouts/partials/i18n.blade.php`.
- Pesan validasi bawaan Laravel: `lang/{locale}/validation.php` (paket laravel-lang).
- PDF untuk 中文/한국어/日本語 dibuat dalam bahasa Inggris karena font PDF belum memuat huruf CJK.
- Menambah teks baru: bungkus dengan `__('...')`, lalu tambahkan kuncinya ke semua file `lang/*.json`.

## Dokumentasi

- `docs/design-system.md` — warna, tipografi, komponen, sketsa layout per role
- `docs/deploy-cpanel.md` — deploy ke cPanel + Cron Job
