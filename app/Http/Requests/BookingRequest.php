<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        $allowed = $booking instanceof Booking
            ? $this->user()->can('update', $booking)
            : $this->user()->can('create', Booking::class);

        // Manager wilayah hanya boleh mencatat untuk villa di wilayahnya.
        return $allowed && (! $this->filled('villa_id') || $this->user()->canAccessVilla((int) $this->input('villa_id')));
    }

    protected function prepareForValidation(): void
    {
        $price = $this->input('total_price');
        $this->merge(['total_price' => $price === null || $price === '' ? null : preg_replace('/[^\d.]/', '', (string) $price)]);
    }

    public function rules(): array
    {
        /** @var Booking|null $booking */
        $booking = $this->route('booking');
        $synced = $booking?->isFromPlatformSync();

        return [
            'villa_id' => [$synced ? 'nullable' : 'required', 'integer', 'exists:villas,id'],
            'platform_id' => [$synced ? 'nullable' : 'required', 'integer', 'exists:booking_platforms,id'],
            'check_in' => [$synced ? 'nullable' : 'required', 'date'],
            'check_out' => [$synced ? 'nullable' : 'required', 'date', 'after:check_in'],
            'booking_ref' => ['nullable', 'string', 'max:100'],
            'guest_name' => ['nullable', 'string', 'max:150'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:99'],
            'guest_phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9][0-9\s().-]*$/'],
            'total_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'status' => ['required', Rule::in(array_map(fn ($status) => $status->value, BookingStatus::selectable()))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'villa_id' => 'villa', 'platform_id' => __('sumber booking'), 'check_in' => 'tanggal check-in',
            'check_out' => 'tanggal check-out', 'booking_ref' => __('kode booking'), 'guest_name' => __('nama tamu'),
            'guest_count' => __('jumlah tamu'), 'guest_phone' => __('No. HP tamu'), 'total_price' => __('harga reservasi'),
        ];
    }

    public function messages(): array
    {
        return ['check_out.after' => __('Tanggal check-out harus setelah check-in (minimal 1 malam).')];
    }
}
