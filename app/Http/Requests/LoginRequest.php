<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bisa email atau username
            'email' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'email / username', 'password' => __('kata sandi')];
    }

    /** Maksimal 5 percobaan gagal per menit per email+IP. */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('email'));
        $field = str_contains($login, '@') ? 'email' : 'username';
        $credentials = [$field => Str::lower($login), 'password' => $this->input('password'), 'is_active' => true];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('Email/username atau kata sandi salah, atau akun sedang dinonaktifkan.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('Terlalu banyak percobaan masuk. Coba lagi dalam :seconds detik.', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
