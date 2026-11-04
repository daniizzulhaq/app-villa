{{-- Kamus terjemahan untuk teks yang dibuat JavaScript (resources/js/app.js → t()). --}}
@php
    $jsKeys = [
        ':label belum sesuai format.', ':label harus berupa angka.', ':label maksimal :max karakter.', ':label maksimal :max.',
        ':label maksimal 2 angka di belakang koma.', ':label minimal :min karakter.', ':label minimal :min.', ':label wajib diisi.',
        ':name lebih dari 10 MB, dilewati.', '+:num lagi', 'Belum ada data', 'Format email belum benar, contoh: nama@email.com',
        'Gagal memuat kalender. Periksa koneksi lalu coba lagi.', 'Gagal menyalin. Salin manual.', 'jt', 'rb', 'M', 'Kolom ini',
        'Link disalin.', 'Maksimal :max foto per nota.', 'Masukkan alamat lengkap, diawali https://', 'Menyimpan…',
        'Tidak ada reservasi di periode ini — villa kosong.', 'Total', 'Ukuran foto maksimal 10 MB.', 'Ya, lanjutkan',
    ];
    $jsMessages = [];
    foreach ($jsKeys as $k) {
        $jsMessages[$k] = __($k);
    }
@endphp
<script type="application/json" id="pm-i18n">{!! json_encode(['locale' => app()->getLocale(), 'messages' => $jsMessages], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
