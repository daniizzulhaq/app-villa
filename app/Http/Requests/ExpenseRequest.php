<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\MonthlyReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        $user = $this->user();

        if ($expense instanceof Expense && ! $user->can('update', $expense)) {
            return false;
        }

        // Staff hanya boleh mencatat untuk villa yang ditugaskan
        return $user->can('createForVilla', [Expense::class, (int) $this->input('villa_id')]);
    }

    protected function prepareForValidation(): void
    {
        // Keterangan opsional demi kecepatan input di lapangan — default: nama kategori.
        $description = $this->filled('description') ? trim((string) $this->input('description')) : null;
        if (! $description && $this->filled('category_id')) {
            $description = \App\Models\ExpenseCategory::whereKey($this->input('category_id'))->value('name');
        }
        $this->merge([
            'amount' => $this->filled('amount') ? preg_replace('/[^\d]/', '', (string) $this->input('amount')) : null,
            'description' => $description,
        ]);
    }

    public function rules(): array
    {
        $max = (int) config('propertyme.upload.max_files', 6);
        /** @var Expense|null $expense */
        $expense = $this->route('expense');
        $isStaff = $this->user()->isStaff();
        $existing = $expense ? $expense->receipts()->whereNotIn('id', (array) $this->input('remove_receipts', []))->count() : 0;

        return [
            'villa_id' => ['required', 'integer', 'exists:villas,id'],
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'expense_date' => ['required', 'date', 'before_or_equal:'.today()->addDay()->toDateString()],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'description' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:3000'],
            // Staff wajib menyertakan minimal satu foto nota
            'receipts' => [$isStaff && $existing === 0 ? 'required' : 'nullable', 'array', 'max:'.max(1, $max - $existing)],
            'receipts.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:'.config('propertyme.upload.max_kb', 10240)],
            'remove_receipts' => ['nullable', 'array'],
            'remove_receipts.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $v->errors()->has('expense_date') && MonthlyReport::isLocked((int) $this->input('villa_id'), $this->input('expense_date'))) {
                $v->errors()->add('expense_date', __('Laporan bulan ini sudah Final dan terkunci. Pilih tanggal lain atau minta Manager membuka kunci.'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'villa_id' => 'villa', 'category_id' => 'kategori', 'expense_date' => 'tanggal', 'amount' => 'nominal',
            'description' => 'keterangan', 'vendor' => 'vendor/toko', 'payment_method' => __('metode bayar'),
            'receipts' => __('foto nota'), 'receipts.*' => __('foto nota'),
        ];
    }

    public function messages(): array
    {
        return [
            'receipts.required' => __('Foto nota wajib diunggah. Ketuk "Foto Nota" untuk memotret.'),
            'amount.required' => __('Nominal wajib diisi.'),
            'amount.min' => __('Nominal harus lebih dari Rp 0.'),
            'expense_date.before_or_equal' => __('Tanggal tidak boleh di masa depan.'),
            'category_id.required' => __('Pilih kategori pengeluaran.'),
        ];
    }
}
