# Pemasukan dan laporan otomatis dari Hostex

Alur: OTA → Hostex → booking/kalender PropertyMe → pemasukan otomatis → dashboard,
laporan bulanan/tahunan, PDF dan Excel. Scheduler Hostex yang sudah berjalan
setiap lima menit juga memperbarui pemasukan. Tidak perlu cron terpisah.

Mode ini mencatat **pendapatan booking**, bukan konfirmasi pencairan atau transfer
dana. Harga dan komisi memakai nilai per stay dari Hostex (`rates.rate` dan
`rates.commission`), bukan total order yang bisa berulang pada beberapa stay.
Referensi: [Hostex Query Reservations](https://api-doc.hostex.io/reference/query-reservations).

## Aktifkan di hosting

Selesaikan pemetaan villa dan perbaikan alokasi lama terlebih dahulu. Untuk kasus
Daluca #6, ikuti opsi `--archive-ical=4` dalam
[panduan empat villa](hostex-four-villas-hosting.md). Pemasukan yang sudah terkait
booking akan mengunci koreksi alokasi sampai ditinjau.

Setelah push/pull kode terbaru, jalankan di Terminal cPanel:

```bash
cd ~/app-villa
php artisan migrate --force
php artisan optimize:clear
```

Tambahkan ke `.env` hosting, sambil mempertahankan token dan koneksi yang sudah ada:

```dotenv
CHANNEL_MANAGER_PROVIDER=hostex
HOSTEX_SYNC_ENABLED=true
HOSTEX_AUTO_INCOME_ENABLED=true
HOSTEX_INCOME_DATE_BASIS=check_in
```

Tanggal check-in digunakan sebagai tanggal pencatatan laporan. Contoh: check-in
30 November dan check-out 3 Desember masuk laporan November. Jika kebijakan
management menggunakan check-out, ubah menjadi `check_out` sehingga contoh itu
masuk Desember. Ini mengatur periode pendapatan booking; tanggal tersebut tidak
menyatakan tanggal uang masuk ke bank. Bulan manual/payout yang sudah ada tetap
menggunakan tanggal pencatatan lamanya.

```bash
php artisan optimize:clear
php artisan propertyme:hostex-sync
```

Sinkron ini mengisi juga pemasukan untuk booking lama dalam rentang data yang
ditarik, walaupun data bookingnya tidak berubah. Rentang bawaan check-in adalah
satu tahun ke belakang sampai satu tahun ke depan. Untuk riwayat lebih lama,
gunakan `--from=YYYY-MM-DD --to=YYYY-MM-DD`. Hanya villa aktif dan sudah dipetakan
yang disinkronkan. Tidak perlu mengaktifkan kembali villa lokal yang memang
sengaja dinonaktifkan untuk deployment ke database hosting yang berbeda.

## Aturan perhitungan dan pembaruan

- Satu `stay_code` memiliki satu pemasukan otomatis. Sinkron berulang memperbarui
  baris yang sama. Jika beberapa stay berbagi satu kode order, masing-masing
  tetap memakai harga dan komisinya sendiri.
- Gross adalah harga booking; komisi dikurangkan sekali untuk memperoleh net.
  Management fee memakai pengaturan/riwayat fee villa dan disimpan sebagai
  snapshot pada pemasukan. Pembaruan harga memakai snapshot tersebut, bukan fee
  villa terbaru yang mungkin berbeda.
- Pendapatan otomatis hanya mencatat status `accepted`. Booking menunggu
  konfirmasi/pembayaran belum dihitung. Pembatalan mengarsipkan pendapatan
  otomatis pada periode yang belum final. Booking yang aktif kembali memulihkan
  entri yang sama. Biaya pembatalan atau penerimaan aktual yang berbeda harus
  dicatat berdasarkan bukti pembayaran, bukan ditebak dari status batal.
- Semua rumus laporan memakai `FinanceCalculator` yang sama dengan pemasukan.
  Kalender memprioritaskan net pemasukan tercatat agar angkanya konsisten dengan
  laporan, sebelum potongan management fee.
- Pemasukan otomatis diberi label **Automatic Hostex income** dan dikelola lewat
  sinkronisasi. Pengeditan/penghapusan manual diblokir agar tidak tertimpa diam-diam.
  Pencatatan pemasukan manual atau impor payout yang dapat dicocokkan ke entri
  otomatis tidak membuat pendapatan kedua untuk booking yang sama.
  Impor payout yang cocok juga tidak menimpa harga booking yang sudah dikelola
  oleh pemasukan otomatis Hostex.

## Data yang memerlukan pemeriksaan

Sistem menampilkan alasan pada detail booking dan jumlah yang perlu diperiksa
pada halaman Pemasukan. Laporan tidak bisa difinalkan selama masih ada masalah
pemasukan Hostex dalam periode tersebut.

- Nilai gross dan komisi harus lengkap serta berdenominasi IDR. Nol komisi valid;
  komisi kosong tidak dianggap nol. USD tidak dikonversi menjadi Rupiah tanpa
  data konversi. Lengkapi data Hostex atau catat pemasukan IDR yang terverifikasi
  secara manual dan hubungkan ke booking terkait.
- Komisi melebihi gross, fee dengan basis custom, tanggal pencatatan yang tidak
  valid, atau alokasi pemasukan yang berbeda ditahan untuk diperiksa.
- Pemasukan manual/import yang sudah terhubung dipertahankan dan tidak ditimpa.
  Jika referensi cocok tetapi belum terhubung ke booking, sistem meminta
  pemeriksaan hubungan tersebut. Pemasukan terhapus juga tidak dipulihkan secara
  otomatis seolah-olah transaksi baru.
- Jika data yang sebelumnya lengkap menjadi tidak lengkap, pemasukan otomatis
  diarsipkan sementara pada bulan draft agar nominal lama tidak terus dihitung.
  Setelah data lengkap, entri yang sama bisa dipulihkan.
- Bulan final tidak diubah otomatis. Perubahan harga, pembatalan atau perpindahan
  tanggal yang mengenai bulan final ditandai untuk ditinjau. Buka kunci bulan
  terkait melalui Laporan, jalankan sinkron lagi, periksa hasil lalu finalkan
  kembali. Tanggal lama dan baru sama-sama diperiksa terhadap penguncian.

Matikan `HOSTEX_AUTO_INCOME_ENABLED` untuk menghentikan perubahan pemasukan
otomatis. Pengaturan tersebut tidak menghapus pemasukan yang sudah tercatat.

## Memulihkan pemasukan terhapus setelah diperiksa

Jika detail booking menampilkan **Pemasukan yang dihapus perlu diperiksa sebelum
pencatatan otomatis**, Manager yang memiliki akses ke villa dapat:

1. Jalankan **Sinkron Hostex** agar nominal booking di PropertyMe terbaru.
2. Buka detail booking yang benar, cocokkan villa, tamu, sumber, kode dan tanggal.
3. Klik **Periksa pemasukan terhapus**. Bandingkan catatan lama dengan tanggal,
   gross, komisi, net dan management fee yang akan dicatat dari Hostex.
4. Klik **Pulihkan dan sinkronkan pemasukan** setelah rinciannya cocok.

Pemulihan memakai ID pemasukan yang sama dan menyimpan catatan, lampiran, serta
riwayat auditnya. Catatan tersebut selanjutnya dikelola sinkronisasi Hostex.
Persentase dan basis fee aslinya dipertahankan, sedangkan nominal mengikuti
data Hostex. Tidak ada perubahan yang dikirim ke Hostex/Airbnb.

Proses ditolak jika ada transaksi lain yang juga cocok, identitas berbeda,
nominal belum lengkap, mata uang selain IDR, fee custom, atau booking belum
Confirmed. Bulan tanggal lama dan tanggal baru harus sama-sama belum Final.
Jika datanya berubah setelah halaman pemeriksaan dibuka, muat ulang rincian.
Sinkronisasi biasa tetap tidak memulihkan pemasukan yang sengaja dihapus.

Fitur ini memerlukan pembaruan file aplikasi, view, route dan terjemahan di
hosting, lalu `php artisan optimize:clear`. Tidak memerlukan migrasi baru atau
build aset baru. Pemulihan data hosting dilakukan melalui langkah di atas,
bukan dengan menyalin database lokal ke hosting.

## Verifikasi

CLI menampilkan jumlah pemasukan `created`, `updated`, `archived`, dan `need
review` per villa. Setelah sinkron pertama:

1. Buka **Pemasukan**, pilih villa dan periode berdasarkan tanggal pencatatannya.
2. Bandingkan net booking di kalender dengan net pemasukan sebelum management fee.
3. Buka laporan bulan yang sama; gross, komisi, fee dan net mengikuti pemasukan.
4. Sinkron ulang. Tidak boleh ada pemasukan baru untuk stay yang sama.

Uji mencakup pembaruan harga, pembatalan/pemulihan, pencegahan duplikasi dengan
pemasukan manual/impor payout, mata uang, komisi nol/kosong, bulan final lama dan
baru, fee snapshot, pembagian periode, akses role dan ekspor PDF/Excel.
