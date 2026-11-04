<?php

namespace App\Http\Requests;

use App\Enums\FeeBasis;
use App\Enums\UserRole;
use App\Models\BookingPlatform;
use App\Models\User;
use App\Models\Villa;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $villa = $this->route('villa');

        return $villa instanceof Villa
            ? $this->user()->can('update', $villa)
            : $this->user()->can('create', Villa::class);
    }

    protected function prepareForValidation(): void
    {
        // Izinkan "12,5" (format Indonesia) untuk persentase
        if ($this->filled('fee_percentage')) {
            $this->merge(['fee_percentage' => str_replace(',', '.', trim((string) $this->input('fee_percentage')))]);
        }
        // Owner, wilayah, % fee, dan status villa hanya diubah Master — nilai dari Manager diabaikan.
        $villa = $this->route('villa');
        if ($villa instanceof Villa && ! $this->user()->isMaster()) {
            $this->merge([
                'owner_id' => $villa->owner_id,
                'region_id' => $villa->region_id,
                'fee_percentage' => (string) $villa->fee_percentage,
                'fee_basis' => $villa->fee_basis->value,
                'fee_basis_note' => $villa->fee_basis_note,
                'fee_effective_from' => null,
                'is_active' => $villa->is_active ? '1' : '0',
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_photo' => $this->boolean('remove_photo'),
            'staff_ids' => array_values(array_filter((array) $this->input('staff_ids', []))),
        ]);
    }

    public function rules(): array
    {
        $platformSlugs = BookingPlatform::icalPlatforms()->pluck('slug')->all();

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_photo' => ['boolean'],
            'owner_id' => ['nullable', 'integer', $this->mustHaveRole(UserRole::Owner)],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'fee_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'fee_basis' => ['required', Rule::enum(FeeBasis::class)],
            'fee_basis_note' => ['nullable', 'required_if:fee_basis,custom', 'string', 'max:255'],
            'fee_effective_from' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'timezone' => ['required', 'timezone:all'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'channels' => ['nullable', 'array'],
            'staff_ids' => ['array'],
            'staff_ids.*' => ['integer', 'distinct', $this->mustHaveRole(UserRole::Staff)],
        ];

        foreach ($platformSlugs as $slug) {
            $rules["channels.$slug"] = ['nullable', 'string', 'max:2000', 'url:http,https'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => __('nama villa'),
            'location' => 'lokasi',
            'photo' => __('foto villa'),
            'owner_id' => 'owner',
            'fee_percentage' => __('persentase management fee'),
            'fee_basis' => __('basis fee'),
            'fee_basis_note' => __('keterangan basis fee'),
            'fee_effective_from' => __('tanggal berlaku fee'),
            'started_at' => __('tanggal mulai dikelola'),
            'timezone' => __('zona waktu'),
            'notes' => 'catatan',
            'channels.airbnb' => __('link iCal Airbnb'),
            'channels.booking-com' => __('link iCal Booking.com'),
            'staff_ids.*' => 'staff',
        ];
    }

    public function messages(): array
    {
        return [
            'fee_basis_note.required_if' => __('Jelaskan basis fee yang disepakati dengan owner.'),
            'channels.*.url' => __('Link iCal harus berupa alamat lengkap yang diawali https://'),
        ];
    }

    private function mustHaveRole(UserRole $role): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($role) {
            if ($value && ! User::whereKey($value)->role($role->value)->exists()) {
                $fail(__('Pengguna yang dipilih bukan :role.', ['role' => $role->shortLabel()]));
            }
        };
    }
}
