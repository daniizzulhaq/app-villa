<?php

namespace App\Http\Requests;

use App\Enums\FeeBasis;
use App\Models\Booking;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\Villa;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $income = $this->route('income');

        $allowed = $income instanceof Income
            ? $this->user()->can('update', $income)
            : $this->user()->can('create', Income::class);

        // Manager wilayah hanya boleh mencatat untuk villa di wilayahnya.
        return $allowed && (! $this->filled('villa_id') || $this->user()->canAccessVilla((int) $this->input('villa_id')));
    }

    protected function prepareForValidation(): void
    {
        $money = fn ($v) => $v === null || $v === '' ? null : preg_replace('/[^\d]/', '', (string) $v);
        $this->merge([
            'gross_amount' => $money($this->input('gross_amount')),
            'platform_fee' => $money($this->input('platform_fee')) ?? '0',
            'other_deduction' => $money($this->input('other_deduction')) ?? '0',
            'fee_base_amount' => $money($this->input('fee_base_amount')),
            'is_net_of_platform' => $this->boolean('is_net_of_platform'),
        ]);
        if ($this->filled('booking_id') && $b = Booking::find($this->input('booking_id'))) {
            $this->merge(['villa_id' => $b->villa_id]);
        }
    }

    public function rules(): array
    {
        /** @var Income|null $income */
        $income = $this->route('income');
        $villa = Villa::find($this->input('villa_id'));

        return [
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')->whereNull('deleted_at')],
            'villa_id' => ['required', 'integer', 'exists:villas,id'],
            'platform_id' => ['required', 'integer', 'exists:booking_platforms,id'],
            'booking_ref' => ['nullable', 'string', 'max:100',
                Rule::unique('incomes', 'booking_ref')->ignore($income?->id)
                    ->where('villa_id', $this->input('villa_id'))->where('platform_id', $this->input('platform_id'))],
            'payment_date' => ['required', 'date', 'before_or_equal:'.today()->addDays(60)->toDateString()],
            'gross_amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'platform_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'other_deduction' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'is_net_of_platform' => ['boolean'],
            'fee_base_amount' => [$villa?->fee_basis === FeeBasis::Custom ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $gross = BigDecimal::of($this->input('gross_amount'));
            $deduct = BigDecimal::of($this->boolean('is_net_of_platform') ? '0' : $this->input('platform_fee', '0'))->plus($this->input('other_deduction', '0'));
            if ($deduct->isGreaterThan($gross)) {
                $v->errors()->add('platform_fee', __('Komisi + potongan lain tidak boleh melebihi gross.'));
            }
            if (MonthlyReport::isLocked((int) $this->input('villa_id'), $this->input('payment_date'))) {
                $v->errors()->add('payment_date', __('Laporan bulan ini sudah Final dan terkunci. Buka kunci di menu Laporan untuk mengubah data.'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'villa_id' => 'villa', 'platform_id' => __('sumber booking'), 'booking_ref' => __('kode booking'),
            'payment_date' => __('tanggal bayar'), 'gross_amount' => __('gross booking income'), 'platform_fee' => __('komisi platform'),
            'other_deduction' => __('potongan lain'), 'fee_base_amount' => __('basis fee'), 'attachments.*' => __('bukti bayar'),
        ];
    }

    public function messages(): array
    {
        return [
            'booking_ref.unique' => __('Kode booking ini sudah tercatat untuk villa & platform yang sama (cegah data dobel).'),
            'gross_amount.min' => __('Gross booking income wajib diisi dan lebih dari 0.'),
        ];
    }
}
