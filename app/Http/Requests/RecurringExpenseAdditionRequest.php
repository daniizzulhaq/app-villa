<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecurringExpenseAdditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('recurring_expense'));
    }

    protected function prepareForValidation(): void
    {
        // Match the Rupiah input used throughout the application; reject negative values.
        $amount = str_replace('.', '', trim((string) $this->input('amount')));
        $this->merge(['amount' => $amount === '' ? null : $amount]);
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'label' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['month' => __('Bulan tambahan'), 'label' => __('Nama tambahan'),
            'amount' => __('Nominal tambahan'), 'note' => __('Catatan tambahan')];
    }
}
