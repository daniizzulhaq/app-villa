# Pemeriksaan keamanan dan integritas data

Tanggal: 11 November 2026. Pemeriksaan ini dilakukan pada kode dan database uji
lokal. Konfigurasi server dan isi database hosting belum diperiksa langsung.

## Hasil dan perbaikan

Pengaman utama sudah memiliki pengujian: pembatasan akses per role/wilayah/villa,
file nota privat, sinkron Hostex berulang tanpa menggandakan pemasukan, penolakan
alokasi booking yang bertentangan, dan penahanan harga/komisi yang belum lengkap.
Sinkron PropertyMe menarik data Hostex; tidak mengubah harga atau pengaturan OTA.

Pemeriksaan ini menemukan jalur impor payout dan pemeriksaan nota yang masih
bergantung pada validasi halaman untuk melindungi bulan Final. Pengecekan itu
sekarang juga dilakukan di dalam transaksi database ketika perubahan disimpan.

- Pemasukan dan pengeluaran tidak dapat dibuat, diubah, dipindahkan keluar/masuk
  periode Final, atau dihapus sebelum Master membuka kunci laporan.
- Impor yang dipratinjau sebelum finalisasi harus diperiksa ulang saat disimpan.
  Baris duplikat juga tidak boleh melengkapi booking pada periode yang terkunci.
- Impor menolak booking yang sudah berpindah villa atau dihapus sejak pratinjau.
- Pemeriksaan nota massal dibatalkan seluruhnya bila ada periode Final.
- Penguncian villa dilakukan sebelum penulisan data agar penyimpanan dan
  finalisasi laporan untuk villa yang sama tidak berjalan bersamaan.
- Finalisasi berulang tidak menambah revisi atau mengirim notifikasi dua kali.

Pembaruan dependensi dibatasi pada patch berikut:

| Paket | Sebelum | Sesudah | Pemakaian |
| --- | --- | --- | --- |
| symfony/http-foundation | 7.4.22 | 7.4.23 | Produksi |
| symfony/http-kernel | 7.4.22 | 7.4.23 | Produksi |
| symfony/routing | 7.4.22 | 7.4.23 | Produksi |
| symfony/yaml | 8.1.10 | 8.1.11 | Pengembangan |

Rujukan patch: [advisory resmi Symfony HTTP Foundation](https://symfony.com/cve-2026-118514)
dan [advisory resmi Symfony YAML](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx).
Advisory pada dependensi tidak dengan sendirinya membuktikan bahwa aplikasi ini
telah dieksploitasi.

## Verifikasi lokal

- `php artisan test --compact`: **192 tes lulus, 1.589 assertions**, termasuk
  sembilan pengujian baru untuk periode Final dan pratinjau impor yang kedaluwarsa.
- `composer audit --locked --no-dev --format=json`: tidak ada advisory atau paket
  produksi yang berstatus abandoned.
- Audit Composer termasuk paket pengembangan: tidak ada advisory. Tiga paket
  pengembangan masih berstatus abandoned: `dragon-code/contracts`,
  `dragon-code/pretty-array`, dan `dragon-code/support`. Karena itu audit lengkap
  tetap mengembalikan status gagal untuk abandoned packages; pemeliharaan paket
  pengembangan ini perlu dijadwalkan terpisah.
- `npm audit --json` dan `npm audit --omit=dev --json`: tidak ada vulnerability
  yang dilaporkan.
- `composer validate --check-lock`, format PHP, dan JSON terjemahan diperiksa.
- `.env` diabaikan oleh Git. Token tidak disalin ke catatan ini.

Tes dan audit dependensi tidak menjamin semua kemungkinan serangan sudah
tertutup. Pengujian ini juga belum merupakan uji beban atau penetration test
terhadap server hosting.

## Yang masih perlu dipastikan di hosting

1. **Tiga booking yang meminta pemeriksaan pemasukan.** Buka detail booking dan
   ikuti alasan yang ditampilkan. Menunggu akhir bulan tidak menyelesaikan data
   yang kurang. Rujuk [panduan pemasukan Hostex](hostex-automatic-income.md).
2. **Kredensial.** Ganti API key yang pernah dibagikan melalui chat jika masih
   aktif, lalu perbarui `.env` di semua lingkungan yang menggunakannya. Ganti
   password admin yang masih memakai password awal atau mudah ditebak.
3. **Konfigurasi produksi.** Pastikan `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL` memakai HTTPS, dan `SESSION_SECURE_COOKIE=true`. Document root harus
   menunjuk folder `public`, bukan akar proyek. Jangan membuka `.env`, backup
   database, atau `storage/app/private` lewat web.
4. **Proxy.** Kode saat ini menerima `TRUSTED_PROXIES=*` secara default. Sesuaikan
   dengan IP/proxy yang benar atau pastikan server di depan aplikasi menyaring
   header forwarded dari klien. Ini perlu informasi konfigurasi hosting; nilai
   proxy belum diubah agar HTTPS yang sudah berjalan tidak terganggu.
5. **Operasional.** Pastikan cron cPanel benar-benar menjalankan scheduler setiap
   menit, hasil sinkron tidak terus gagal, dan backup database beserta file nota
   disimpan di luar direktori publik serta pernah diuji pemulihannya.

Pendapatan otomatis tetap merupakan pendapatan booking berdasarkan tanggal
pengakuan yang dipilih, bukan bukti dana sudah ditransfer ke rekening. Finalisasi
laporan membutuhkan pemeriksaan pemasukan, pengeluaran, dan data yang tertahan.

## Memasang perbaikan ini

Perubahan masih di lokal dan belum di-push atau diterapkan ke hosting. Setelah
kode beserta `composer.lock` di-push sendiri, siapkan backup database dan file
nota, kemudian jalankan di Terminal cPanel:

```bash
cd ~/app-villa
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
composer audit --no-dev
php artisan propertyme:hostex-sync
php artisan schedule:list
```

Perbaikan audit ini tidak menambah migrasi database atau aset frontend. Gunakan
`composer install` agar versi mengikuti lock file yang telah diuji. Daftar
`schedule:list` hanya menampilkan jadwal aplikasi; tetap cek Cron Jobs cPanel
untuk memastikan jadwal itu benar-benar dieksekusi.
