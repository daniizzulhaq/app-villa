<x-layouts.app :title="$expense->status === \App\Enums\ExpenseStatus::Returned ? __('Perbaiki Nota') : __('Edit Pengeluaran')" :fab="false">
    <x-ui.page-header :title="$expense->status === \App\Enums\ExpenseStatus::Returned ? __('Perbaiki Nota') : __('Edit Pengeluaran')"
                      :back="auth()->user()->isStaff() ? route('staff.activity') : route('expenses.show', $expense)" :back-label="__('Kembali')" />
    @include('expenses._form')
</x-layouts.app>
