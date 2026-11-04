<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return ['current_password' => __('kata sandi saat ini'), 'password' => __('kata sandi baru')];
    }

    public function messages(): array
    {
        return ['current_password.current_password' => __('Kata sandi saat ini tidak sesuai.')];
    }
}
