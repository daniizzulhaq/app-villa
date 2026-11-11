# Setup empat villa Hostex di PropertyMe

Pemetaan diverifikasi melalui API Hostex pada 11 November 2026. ID villa hosting
berasal dari URL PropertyMe yang dikonfirmasi pemilik. ID lokal berbeda dari ID
hosting; jangan menyalin database lokal ke hosting.

| Villa | ID PropertyMe lokal | ID PropertyMe hosting | ID properti Hostex |
|---|---|---|---|
| Avora | 3 | 2 | 12840364 |
| Sofi 1 | 4 | 8 | 12840493 |
| Sofi 2 | 5 | 9 | 12840494 |
| Casa Daluca / Daluca | 6 | 6 | 12840495 |

Pada saat verifikasi, Avora, Sofi 1 dan Daluca sudah memiliki listing Airbnb dan
Booking.com. Sofi 2 baru memiliki listing Airbnb. Jumlah rate plan Booking.com
tidak menambah jumlah villa. Booking.com yang sudah terhubung belum tentu
memiliki reservasi yang dikirimkan Hostex.

## Pembaruan hosting yang sudah berjalan

Push dan pull kode seperti biasa. Pengaturan Hostex disimpan dalam database
masing-masing lingkungan; `git push` tidak memindahkan pemetaan lokal.
Ketiga villa hosting sudah tersedia:

- Casa Daluca: `https://propertymevillas.com/villas/6`
- Sofi 1: `https://propertymevillas.com/villas/8`
- Sofi 2: `https://propertymevillas.com/villas/9`

Pertahankan `.env` hosting dan token Hostex yang sudah berfungsi. Pastikan nilai
berikut tetap aktif:

```dotenv
CHANNEL_MANAGER_PROVIDER=hostex
HOSTEX_SYNC_ENABLED=true
```

`HOSTEX_ACCESS_TOKEN` harus berisi token Hostex yang sama dengan koneksi yang
sudah digunakan; jangan menaruh token dalam Git. Dari Terminal cPanel:

```bash
cd ~/app-villa
php artisan migrate --force
php artisan optimize:clear
php artisan propertyme:hostex-check
```

Pastikan empat properti pada tabel di atas muncul. Lalu hubungkan tiga villa
tambahan menggunakan **ID hosting** berikut:

```bash
php artisan propertyme:hostex-map 6 12840495
php artisan propertyme:hostex-map 8 12840493
php artisan propertyme:hostex-map 9 12840494
```

Avora sudah menggunakan pemetaan `2` ke `12840364`; tidak perlu membuat villa
baru. Perintah pemetaan aman diulang dengan pasangan ID yang sama. Perintah ini
tidak mengganti owner, wilayah, staff, fee, foto, catatan atau data keuangan villa
yang sudah ada. Status villa harus aktif agar bisa mengimpor booking.

## Impor booking ke tiga villa tambahan

Setelah pemetaan, buka detail villa atau **Status Sinkronisasi**. Jika villa
masih memiliki iCal aktif, muncul tombol **Disable iCal** (bahasa Indonesia:
**Nonaktifkan iCal**). Klik tombol itu, lalu konfirmasi. Lakukan pada villa yang
beralih ke Hostex saja, termasuk Avora jika iCal-nya masih aktif.

Tombol menghentikan impor iCal untuk villa tersebut. Link dan riwayat sinkron
tetap disimpan, begitu pula booking lama, catatan dan seluruh transaksi.
Villa lain yang memakai iCal tetap berjalan. Tombol hanya tersedia bagi Master
atau Manager yang memiliki akses villa dan hanya setelah villa dipetakan ke
Hostex. Setelah nonaktif, halaman menampilkan status iCal nonaktif.

Lalu jalankan:

```bash
php artisan propertyme:hostex-sync --villa=6
php artisan propertyme:hostex-sync --villa=8
php artisan propertyme:hostex-sync --villa=9
```

Hasil yang diharapkan berbentuk `created`, `updated`, `unchanged`, dan `skipped`
tanpa pesan error. Impor lokal pertama berhasil memasukkan 11 booking Daluca,
2 booking Sofi 1 dan 13 booking Sofi 2; sinkron ulang tidak membuat booking baru.
Jumlah hosting dapat berbeda jika ada booking lama atau reservasi baru di OTA.
Rentang bawaan mengambil tanggal check-in satu tahun ke belakang sampai satu
tahun ke depan, bukan seluruh riwayat tanpa batas.

Jika muncul `A booking reference already exists outside Hostex`, lihat dahulu
hasil rekonsiliasi untuk villa yang gagal, misalnya Sofi 1:

```bash
php artisan propertyme:hostex-reconcile --villa=8
```

Jika hasilnya **0 conflicts**, sambungkan booking lama yang cocok persis, lalu
ulangi sinkron:

```bash
php artisan propertyme:hostex-reconcile --villa=8 --apply
php artisan propertyme:hostex-sync --villa=8
```

Ganti `8` dengan `6` atau `9` sesuai villa yang gagal. Rekonsiliasi mempertahankan
ID booking, harga lokal, catatan dan tautan pemasukan. Jika ada konflik, periksa
hasilnya sebelum melanjutkan.

Jika muncul `A Hostex stay is already assigned to another local villa`, booking
mungkin sudah terimpor ke Avora ketika listing sebelumnya masih digabung.
Rekonsiliasi referensi di atas tidak memindahkan booking antarvilla. Gunakan
pratinjau alokasi berikut dari Terminal hosting setelah kode diperbarui:

```bash
php artisan optimize:clear
php artisan propertyme:hostex-reallocate
```

Pratinjau tidak mengubah data. Tabel menampilkan ID booking, ID villa saat ini,
ID villa tujuan sesuai Hostex, tanggal, jumlah pemasukan terkait, dan alasan
konflik. Untuk membatasi pemeriksaan ke Sofi 1, gunakan `--villa=8`.

Jika tabel memastikan villa saat ini adalah Avora **#2**, tujuan sesuai tabel
pemetaan di awal, dan **0 conflicts**, terapkan satu villa setiap kali:

```bash
# Casa Daluca: dari villa hosting #2 ke #6
php artisan propertyme:hostex-reallocate --villa=6 --from-villa=2 --apply
php artisan propertyme:hostex-sync --villa=6

# Sofi 1: dari villa hosting #2 ke #8
php artisan propertyme:hostex-reallocate --villa=8 --from-villa=2 --apply
php artisan propertyme:hostex-sync --villa=8

# Sofi 2: dari villa hosting #2 ke #9
php artisan propertyme:hostex-reallocate --villa=9 --from-villa=2 --apply
php artisan propertyme:hostex-sync --villa=9
```

Gunakan `--from` dan `--to` yang sama pada pratinjau dan penerapan jika memilih
rentang check-in khusus. `--from-villa` adalah ID villa PropertyMe saat ini,
bukan ID Hostex. Jika villa asal pada pratinjau berbeda dari #2, periksa pemetaan
asal terlebih dahulu sebelum menerapkan perubahan.

Perintah hanya memindahkan booking Hostex dengan `stay_code` yang sama,
platform, referensi dan kedua tanggal yang sesuai. Alokasi diverifikasi melalui
API properti tujuan dan asal; booking harus sudah tidak muncul di properti
Hostex asal. ID booking, catatan, harga, UID iCal lama dan field lain dipertahankan.
Tidak ada penulisan ke OTA atau transaksi pemasukan.

Booking terhapus, sumber bukan Hostex, pemasukan terkait (termasuk yang telah
dihapus atau yang cocok berdasarkan referensi), bentrok booking tujuan, atau
data yang berbeda akan ditahan sebagai konflik. Jika satu booking bermasalah,
seluruh penerapan untuk villa itu dibatalkan. Jika ada konflik, kirim hasil
pratinjau untuk pemeriksaan; jangan menghapus booking atau memindahkannya lewat
SQL massal.

## Sinkron otomatis dan pemeriksaan hasil

Cron Laravel hosting yang sudah berjalan cukup dipakai kembali. Tidak perlu cron
terpisah per villa. `php artisan schedule:list` menampilkan tugas
`propertyme:hostex-sync` setiap lima menit; cron `schedule:run` harus tetap
berjalan setiap menit. Jadwal otomatis mengambil semua villa aktif yang sudah
dipetakan ke Hostex.

Buka kalender atau daftar booking di masing-masing villa untuk melihat hasil.
Pengaturan owner, wilayah, staff dan fee tetap memakai data hosting. Owner dan
staff memerlukan penugasan yang sesuai agar dapat melihat villa tersebut.

Sinkron menarik data booking untuk pelaporan. Impor tidak menulis harga atau
ketersediaan ke OTA dan tidak membuat transaksi pemasukan secara otomatis.
