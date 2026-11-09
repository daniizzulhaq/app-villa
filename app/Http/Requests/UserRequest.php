<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()->can('update', $user)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => ($e = mb_strtolower(trim((string) $this->input('email')))) === '' ? null : $e,
            'username' => ($u = mb_strtolower(trim((string) $this->input('username')))) === '' ? null : $u,
            'is_active' => $this->boolean('is_active'),
            'villa_ids' => array_values(array_filter((array) $this->input('villa_ids', []))),
            'region_ids' => array_values(array_filter((array) $this->input('region_ids', []))),
        ]);
    }

    public function rules(): array
    {
        /** @var User|null $editing */
        $editing = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            // Email opsional — tapi minimal salah satu (email / username) harus ada untuk login.
            'email' => ['nullable', 'required_without:username', 'email', 'max:190', Rule::unique('users', 'email')->ignore($editing?->id)],
            'username' => ['nullable', 'required_without:email', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($editing?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'is_active' => ['boolean'],
            'villa_ids' => ['array'],
            'villa_ids.*' => ['integer', 'distinct', 'exists:villas,id'],
            'region_ids' => ['array'],
            'region_ids.*' => ['integer', 'distinct', 'exists:regions,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            /** @var User|null $editing */
            $editing = $this->route('user');
            $actor = $this->user();
            $role = UserRole::tryFrom((string) $this->input('role'));

            // Manager wilayah hanya boleh membuat / mengubah akun Staff.
            if ($role && ! $actor->can('assignRole', [User::class, $role])) {
                $v->errors()->add('role', __('Anda hanya dapat mengelola akun Staff.'));
            }

            // Manager wilayah hanya boleh menugaskan Staff ke villa di wilayahnya.
            $allowed = $actor->accessibleVillaIds();
            if ($allowed !== null && array_diff(array_map('intval', (array) $this->input('villa_ids', [])), $allowed)) {
                $v->errors()->add('villa_ids', __('Anda hanya dapat memilih villa di wilayah Anda.'));
            }

            // Cegah menurunkan role / menonaktifkan akun sendiri.
            if ($editing && $editing->is($actor)) {
                if ($this->input('role') !== $actor->roleEnum()?->value) {
                    $v->errors()->add('role', __('Anda tidak dapat mengubah role akun Anda sendiri.'));
                }
                if (! $this->boolean('is_active')) {
                    $v->errors()->add('is_active', __('Anda tidak dapat menonaktifkan akun Anda sendiri.'));
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => __('nama'),
            'phone' => __('nomor HP'),
            'password' => __('kata sandi'),
            'villa_ids' => __('villa'),
            'villa_ids.*' => __('villa'),
            'region_ids.*' => __('wilayah'),
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => __('Isi email atau username — salah satu wajib untuk login.'),
            'username.required_without' => __('Isi username atau email — salah satu wajib untuk login.'),
            'username.regex' => __('Username hanya boleh huruf kecil, angka, titik, strip, atau garis bawah (tanpa spasi).'),
            'phone.regex' => __('Nomor HP hanya boleh berisi angka, spasi, +, -, atau tanda kurung.'),
        ];
    }
}
