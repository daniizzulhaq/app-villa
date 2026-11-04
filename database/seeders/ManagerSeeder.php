<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Satu akun Master (manager utama) awal — akses semua wilayah & villa. Kredensial dari .env (MANAGER_EMAIL, MANAGER_PASSWORD).
 * Jika MANAGER_PASSWORD kosong, dibuat kata sandi acak dan ditampilkan SEKALI di terminal.
 */
class ManagerSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('propertyme.manager.email');

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->command?->info("Akun Manager {$email} sudah ada — dilewati.");

            return;
        }

        $password = config('propertyme.manager.password') ?: Str::password(14, symbols: false);

        $user = User::create([
            'name' => config('propertyme.manager.name'),
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        $user->assignRole(UserRole::Master->value);

        $this->command?->warn('Akun Master (manager utama) dibuat:');
        $this->command?->line("  Email     : {$email}");
        if (! config('propertyme.manager.password')) {
            $this->command?->line("  Kata sandi: {$password}   ← simpan sekarang, lalu ganti di menu Profil.");
        }
    }
}
