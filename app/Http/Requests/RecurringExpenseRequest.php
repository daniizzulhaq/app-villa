<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\RecurringExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('recurring_expense');

        return $this->user()->isManager()
            && (! $rule || $this->user()->can('update', $rule))
            && $this->user()->canAccessVilla((int) $this->input('villa_id'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->filled('amount') ? preg_replace('/[^\d]/', '', (string) $this->input('amount')) : null,
        ]);
    }

    public function rules(): array
    {
        $rule = $this->route('recurring_expense');

        return [
            'villa_id' => ['required', 'integer', Rule::exists('villas', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:3000'],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'start_month' => [$rule instanceof RecurringExpense ? 'sometimes' : 'required', 'date_format:Y-m', 'after_or_equal:2000-01',
                ...($rule ? [Rule::in([$rule->starts_on->format('Y-m')])] : [])],
            'end_month' => ['nullable', 'date_format:Y-m', 'after_or_equal:'.($rule?->starts_on->format('Y-m') ?? $this->input('start_month'))],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'villa_id' => __('Villa'), 'category_id' => __('Kategori'),
            'description' => __('Nama pengeluaran'), 'amount' => __('Nominal pokok per bulan'),
            'day_of_month' => __('Tanggal pencatatan setiap bulan'), 'start_month' => __('Bulan mulai'),
            'end_month' => __('Bulan berakhir (opsional)'), 'is_active' => __('Status jadwal'),
        ];
    }
}
