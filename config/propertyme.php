<?php

return [
    // Provider untuk integrasi channel manager eksternal.
    'channel_manager_provider' => env('CHANNEL_MANAGER_PROVIDER', 'hostex'),

    // Jumlah villa per akun; Master tetap mengakses seluruh villa.
    'villa_limits' => ['owner' => 20, 'staff' => 20, 'manager' => 20],

    // Akun Manager pertama (dipakai ManagerSeeder). Kosongkan password → dibuat acak & ditampilkan sekali.
    'manager' => [
        'name' => env('MANAGER_NAME', 'Manager PropertyMe'),
        'email' => env('MANAGER_EMAIL', 'manager@propertyme.test'),
        'password' => env('MANAGER_PASSWORD'),
    ],

    // Kirim notifikasi juga lewat email (selain in-app)
    'notify_by_email' => (bool) env('NOTIFY_BY_EMAIL', true),

    // Jam kirim pengingat jadwal besok ke Staff
    'tomorrow_reminder_at' => env('TOMORROW_REMINDER_AT', '17:00'),

    // Batas unggah foto (KB), sisi terpanjang setelah kompres (px), jumlah foto per nota
    'upload' => [
        'max_kb' => 10240,
        'max_side' => 1600,
        'max_files' => 6,
    ],

    // Bahasa yang tersedia di pemilih bahasa (kode => nama asli bahasa)
    'locales' => [
        'en' => 'English',
        'fr' => 'Français',
        'nl' => 'Nederlands',
        'de' => 'Deutsch',
        'id' => 'Bahasa Indonesia',
        'ru' => 'Русский',
        'zh_CN' => '中文',
        'ko' => '한국어',
        'ja' => '日本語',
    ],
];
