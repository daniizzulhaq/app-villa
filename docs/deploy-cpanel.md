# Panduan Deploy PropertyMe ke cPanel

Target: hosting cPanel dengan PHP 8.2+ dan MySQL/MariaDB. Tidak perlu Node.js atau SSH supervisor di server.

## 1. Persiapan di komputer lokal

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # menghasilkan public/build
```

Kompres seluruh folder proyek **kecuali** `node_modules`, `.env`, `storage/logs/*`, dan `tests`.

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
```

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
APP_LOCALE=id

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
| Tiap jam | `propertyme:advance-bookings` — status Checked-in / Checked-out |
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
