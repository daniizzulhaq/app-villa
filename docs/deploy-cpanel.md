# Panduan Deploy PropertyMe ke cPanel

Target: hosting cPanel dengan PHP 8.2+ dan MySQL/MariaDB. Tidak perlu Node.js atau SSH supervisor di server.

## 1. Persiapan di komputer lokal

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # menghasilkan public/build
```

Kompres seluruh folder proyek **kecuali** `node_modules`, `.env`, `storage/logs/*`, dan `tests`.

Folder `public/build` **wajib ikut diunggah**, termasuk `manifest.json`, seluruh CSS,
JavaScript, dan font di `assets/`. Sertakan juga `public/images` untuk foto dan logo.
Jangan unggah `public/hot`: file ini hanya untuk Vite saat development.
Jika deploy lewat Git, pastikan file baru di `public/build/assets` ikut di-commit;
aturan `.gitignore` hanya mengabaikan `/build` di akar proyek, bukan `public/build`.

## 2. Ekstensi PHP yang wajib aktif (cPanel → Select PHP Version → Extensions)

`bcmath`, `gd`, `intl`, `mbstring`, `pdo_mysql`, `zip`, `fileinfo`, `curl`, `openssl`, `xml`, `dom`.
Atur juga `memory_limit` ≥ 256M, `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 64M (upload beberapa foto nota sekaligus).

## 3. Struktur folder di server

Letakkan aplikasi **di luar** `public_html` agar `.env` & `storage` tidak bisa diakses dari web:

```
/home/USER/propertyme/        ← seluruh isi proyek
/home/USER/public_html/       ← isi folder public/ (atau arahkan domain ke /home/USER/propertyme/public)
```

**Opsi A (disarankan):** cPanel → Domains → ubah *Document Root* domain/subdomain menjadi `/home/USER/propertyme/public`.

**Opsi B:** salin isi `propertyme/public/*` ke `public_html/`, lalu ubah dua baris di `public_html/index.php`:

```php
require __DIR__.'/../propertyme/vendor/autoload.php';
$app = require_once __DIR__.'/../propertyme/bootstrap/app.php';
$app->usePublicPath(__DIR__);
```

Letakkan `build/` dan `images/` langsung di `public_html/`, sejajar dengan `index.php`.
`usePublicPath` memastikan Laravel membaca manifest dari folder publik yang sama.

## 4. Database

cPanel → MySQL Databases: buat database `USER_propertyme` dan user dengan hak **ALL PRIVILEGES**.

## 5. File `.env`

Salin `.env.example` menjadi `.env`, lalu isi:

```env
APP_NAME=PropertyMe
APP_ENV=production
APP_DEBUG=false
APP_URL=https://propertyme.domainanda.com
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=USER_propertyme
DB_USERNAME=USER_pmuser
DB_PASSWORD=********

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=mail.domainanda.com
MAIL_PORT=465
MAIL_USERNAME=noreply@domainanda.com
MAIL_PASSWORD=********
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@domainanda.com
MAIL_FROM_NAME="PropertyMe"
NOTIFY_BY_EMAIL=true

MANAGER_NAME="Nama Manager"
MANAGER_EMAIL=manager@domainanda.com
MANAGER_PASSWORD=           # kosongkan → dibuat acak & ditampilkan sekali
```

## 6. Perintah pertama kali (cPanel → Terminal, atau SSH)

```bash
cd ~/propertyme
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # role, platform, 16 kategori, akun Manager — catat kata sandinya
php artisan storage:link             # untuk foto villa (foto nota tetap privat)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Jangan jalankan `DemoSeeder` di produksi (seeder ini menolak berjalan saat `APP_ENV=production`).

Tanpa Terminal? Buat file sementara `public/setup.php` berisi `Artisan::call(...)`, jalankan sekali, lalu **hapus**.

Jika `storage:link` tidak didukung hosting, buat symlink lewat Cron sekali:
`ln -s /home/USER/propertyme/storage/app/public /home/USER/public_html/storage`

## 7. Cron Job (wajib) — cPanel → Cron Jobs

Tambahkan **satu** cron, setiap menit (`* * * * *`):

```
cd /home/USER/propertyme && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

(Periksa path PHP di cPanel → *Select PHP Version*; bisa juga `/opt/cpanel/ea-php82/root/usr/bin/php`.)

Scheduler ini menjalankan:

| Jadwal | Tugas |
|---|---|
| Tiap 30 menit | `propertyme:sync-ical` — sinkron iCal Airbnb & Booking.com |
| Tiap jam | `propertyme:advance-bookings` — sesuaikan status lama menjadi Confirmed |
| Harian 17:00 | `propertyme:notify-tomorrow` — jadwal besok ke Staff |
| Tiap menit | `queue:work --stop-when-empty` — kirim email notifikasi |

Cek manual: `php artisan schedule:list` dan `php artisan propertyme:sync-ical`.

## 8. Izin folder

```bash
chmod -R 775 storage bootstrap/cache
```

## 9. Update versi

```bash
php artisan down
# unggah file baru (vendor & public/build hasil build lokal)
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 10. Backup

- cPanel → Backup: database harian.
- Folder `storage/app/private` (foto nota & bukti bayar) dan `storage/app/public` (foto villa) ikut dibackup.

## 11. Checklist setelah deploy

- [ ] Login Manager berhasil, lalu ganti kata sandi di Profil
- [ ] Tambah villa + link iCal → klik **Sinkron Sekarang** di Status Sinkronisasi
- [ ] Salin link ekspor iCal villa → tempel di Airbnb/Booking.com (Import calendar)
- [ ] Buat akun Staff, upload satu nota dari HP (kamera terbuka)
- [ ] Notifikasi email masuk ke Manager
- [ ] `APP_DEBUG=false` dan https aktif

## 12. CSS atau JavaScript tidak termuat setelah update

1. Unggah `public/build` **secara utuh dari build yang sama**. Manifest baru dengan
   file CSS/JS lama menyebabkan halaman kehilangan tampilan. Untuk deploy lewat Git,
   sertakan file CSS dan JS baru di commit, bukan hanya `manifest.json`.
2. Pastikan folder `build/` berada di document root yang melayani `index.php`:
   `propertyme/public/build/` untuk Opsi A atau `public_html/build/` untuk Opsi B.
   Pada Opsi B, gunakan `$app->usePublicPath(__DIR__);` seperti contoh di atas.
3. Pastikan `public/hot` tidak ada di server; file ini membuat `@vite` memakai alamat
   server development. Pada Opsi B, periksa juga `public_html/hot`.
4. Atur `APP_URL` ke URL HTTPS hosting. Jika ada `ASSET_URL`, pastikan nilainya adalah
   alamat publik tempat folder `build/` berada; kosongkan jika tidak memakai CDN.
5. Jalankan `php artisan optimize:clear`, kemudian muat ulang dengan **Ctrl + F5**.
6. Buka URL CSS yang tercantum di source HTML. Respons harus berstatus **200** dan
   berisi CSS, bukan halaman login/404. Jika gagal, periksa lokasi upload dan document root.

Untuk pembaruan aset melalui File Manager, ZIP aset dapat berisi `build/` dan
`images/` di tingkat teratas. Ekstrak ZIP langsung ke document root, bukan membuat
folder `public/` tambahan di dalam `public_html/`.

## 13. Update kolom Link iCal lainnya

Kolom opsional ketiga di bawah Booking.com menerima **satu link ekspor iCal** dari
platform tambahan. Platform harus menyediakan feed iCal; alamat halaman listing
atau API bukan link kalender. Reservasi yang diimpor dicatat sebagai **Lainnya**
dan ikut sinkronisasi otomatis setiap 30 menit menggunakan cron yang sudah ada.

Unggah file aplikasi yang berubah, termasuk `resources/views/villas/_form.blade.php`,
`resources/views/sync/index.blade.php`, `app/Http/Requests/VillaRequest.php`,
`app/Console/Commands/SyncIcalCommand.php`, `routes/console.php`, seluruh file JSON
di `lang/`, `database/seeders/BookingPlatformSeeder.php`, dan migration
`database/migrations/2026_11_10_100001_enable_ical_for_other_booking_platform.php`.
Kemudian jalankan dari direktori aplikasi di Terminal cPanel:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Jika hanya menggunakan File Manager dan phpMyAdmin, unggah file aplikasi tersebut
lalu pilih database aplikasi di phpMyAdmin dan jalankan SQL berikut untuk
mengaktifkan kolom pada platform Lainnya yang sudah ada:

SQL juga tersedia di `docs/sql/enable-other-ical.sql` untuk diimpor langsung.

```sql
UPDATE `booking_platforms`
SET `supports_ical` = 1
WHERE `slug` = 'lainnya';
```

SQL ini mempertahankan ID platform, reservasi, dan link Airbnb/Booking.com.
Migration tetap aman dijalankan kemudian jika SQL sudah diterapkan. Bila hosting
menggunakan cache view, kosongkan cache melalui `php artisan view:clear` atau
hapus hanya file view terkompilasi `*.php` di `storage/framework/views/` melalui
File Manager. Biarkan folder dan `.gitignore` tetap ada.

Setelah update, buka Edit Villa, isi Link iCal lainnya, simpan, lalu gunakan
Sinkron sekarang untuk memeriksa hasil tanpa menunggu jadwal cron.
