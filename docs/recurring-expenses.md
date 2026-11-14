# Pengeluaran rutin bulanan

Master dan Manager membuka **Keuangan → Pengeluaran Rutin** atau tombol pada halaman Pengeluaran. Akses Manager mengikuti wilayah villa.

Isi villa, kategori, nama pengeluaran, nominal pokok, tanggal 1–31, bulan mulai, bulan berakhir opsional, status, penerima, metode bayar, dan catatan. Tax menggunakan nominal tetap. Jadwal aktif yang sudah jatuh tempo langsung dibuat saat disimpan. Tanggal 29–31 disesuaikan ke hari terakhir bulan pendek, mengikuti zona waktu villa.

Transaksi otomatis berstatus Valid dan langsung masuk ke perhitungan laporan. Ini merupakan pencatatan pengeluaran, bukan transfer dana atau bukti bahwa pembayaran sudah dilakukan.

Untuk lembur, buka **Pengeluaran → Detail → Edit**, lalu tambah nama, nominal, dan catatan tambahan. Nominal pokok bulan tersebut juga dapat dikoreksi. Total dihitung server dari nominal pokok dan semua tambahan. Edit atau hapus transaksi hanya memengaruhi transaksi itu; menghapusnya tidak membuat sistem mencatatnya lagi. Ubah pengaturan rutin untuk pencatatan berikutnya. Riwayat tidak ditulis ulang. Saat pengaturan diubah, jadwal lama yang terlewat diproses dengan nominal lama terlebih dahulu.

Nonaktifkan jadwal untuk berhenti sementara. Saat diaktifkan lagi, pencatatan dimulai kembali dari bulan berjalan atau bulan mulai jika masih di masa depan; bulan-bulan selama jeda tidak dibuat ulang. Hapus jadwal untuk menghentikannya tanpa menghapus riwayat transaksi.

## Tambahan bulan tertentu

Tombol **Tambah bulan ini** di samping **Edit pengaturan** membuka form khusus tambahan per bulan. Pilih bulan, isi nama tambahan (misalnya Lembur), nominal, dan catatan. Jika transaksi sudah tercatat, total langsung diperbarui dengan mempertahankan koreksi dan tambahan yang sudah ada. Jika jadwal belum jatuh tempo (misalnya gaji akhir bulan), tambahan disimpan terpisah dan belum masuk laporan; scheduler menggabungkannya ke transaksi bulan tersebut pada tanggal pencatatan. Tambahan yang masih menunggu bisa dihapus dari halaman ini; tambahan yang sudah tercatat bisa dikoreksi dari detail transaksi. Bulan berikutnya tetap memakai nominal pokok rutin.

## Aktivasi di hosting

Jalankan migrasi saat rilis:

```bash
php artisan migrate --force
```

Scheduler Laravel yang sudah dipakai sinkronisasi dan antrean juga menjalankan `propertyme:generate-recurring-expenses` setiap jam. Cron cPanel harus tetap aktif setiap menit:

```cron
* * * * * cd /home/USER/propertyme && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Untuk mengejar jadwal yang tertinggal atau memeriksa hasil secara manual:

```bash
php artisan propertyme:generate-recurring-expenses
```

Periode yang terlewat dikejar tanpa duplikasi, termasuk saat beberapa proses berjalan. Laporan Final tidak disentuh. Periode terkunci dilaporkan pada output command (exit 1), sementara bulan lain yang terbuka tetap dibuat. Setelah laporan dibuka kunci, periode yang tertinggal akan dicatat pada proses berikutnya. Perubahan pengaturan yang memiliki periode terkunci belum tercatat ditolak sampai laporan dibuka kunci. Jadwal untuk villa atau kategori nonaktif tidak dijalankan dan akan dikejar jika diaktifkan kembali.

Verifikasi fitur: `php artisan test --compact --filter=RecurringExpenseTest`.
