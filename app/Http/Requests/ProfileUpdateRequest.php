<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => ($e = mb_strtolower(trim((string) $this->input('email')))) === '' ? null : $e]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // Akun tanpa username wajib punya email (agar tetap bisa login).
            'email' => [$this->user()->username ? 'nullable' : 'required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'phone' => __('nomor HP')];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Nomor HP hanya boleh berisi angka, spasi, +, -, atau tanda kurung.')];
    }
}
