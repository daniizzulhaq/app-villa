<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\ReportStatus;
use App\Http\Requests\RecurringExpenseAdditionRequest;
use App\Http\Requests\RecurringExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MonthlyReport;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAddition;
use App\Models\Villa;
use App\Services\RecurringExpenseService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RecurringExpenseController extends Controller
{
    public function __construct(private RecurringExpenseService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', RecurringExpense::class);
        $rules = $request->user()->restrictToVillas(RecurringExpense::query())
            ->with(['villa', 'category'])->latest()->paginate(25);
        $lockedPeriods = MonthlyReport::whereIn('villa_id', $rules->pluck('villa_id'))
            ->where('status', ReportStatus::Final->value)
            ->get(['villa_id', 'year', 'month'])
            ->map(fn ($report) => $report->villa_id.':'.sprintf('%04d-%02d', $report->year, $report->month));

        return view('recurring-expenses.index', compact('rules', 'lockedPeriods'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', RecurringExpense::class);

        return view('recurring-expenses.form', $this->formData($request, new RecurringExpense([
            'is_active' => true, 'day_of_month' => 1, 'starts_on' => today()->startOfMonth(),
        ])));
    }

    public function store(RecurringExpenseRequest $request)
    {
        $this->service->save($request->validated(), $request->user());

        return redirect()->route('recurring-expenses.index')->with('success', __('Pengeluaran rutin disimpan. Jadwal yang sudah jatuh tempo langsung tercatat.'));
    }

    public function edit(Request $request, RecurringExpense $recurringExpense)
    {
        $this->authorize('update', $recurringExpense);

        return view('recurring-expenses.form', $this->formData($request, $recurringExpense));
    }

    public function update(RecurringExpenseRequest $request, RecurringExpense $recurringExpense)
    {
        $this->service->save($request->validated(), $request->user(), $recurringExpense);

        return redirect()->route('recurring-expenses.index')->with('success', __('Pengaturan rutin diperbarui. Transaksi yang sudah tercatat tetap.'));
    }

    public function destroy(Request $request, RecurringExpense $recurringExpense)
    {
        $this->authorize('delete', $recurringExpense);
        $this->service->delete($recurringExpense, $request->user());

        return redirect()->route('recurring-expenses.index')->with('success', __('Jadwal rutin dihapus. Riwayat pengeluaran tetap tersimpan.'));
    }

    public function additions(Request $request, RecurringExpense $recurringExpense)
    {
        $this->authorize('update', $recurringExpense);
        $recurringExpense->load(['villa', 'category']);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $data['month'] ?? Carbon::today($recurringExpense->villa?->timezone ?: config('app.timezone'))->format('Y-m');
        $period = Carbon::parse($month.'-01');
        $expense = Expense::withTrashed()->where('recurring_expense_id', $recurringExpense->id)->whereDate('recurring_period', $period)->first();
        if ($expense) {
            $this->authorize('view', $expense);
        }
        $pending = RecurringExpenseAddition::where('recurring_expense_id', $recurringExpense->id)
            ->whereDate('period', $period)->whereNull('applied_expense_id')->orderBy('id')->get();
        $additions = $expense ? ($expense->additions ?? []) : $pending->toArray();
        $base = $expense?->base_amount ?? $recurringExpense->amount;
        $total = BigDecimal::of($base);
        foreach ($additions as $addition) {
            $total = $total->plus($addition['amount']);
        }
        $locked = MonthlyReport::isLocked($recurringExpense->villa_id, $period)
            || ($expense && ! $request->user()->can('update', $expense));
        $due = $period->copy()->day(min($recurringExpense->day_of_month, $period->daysInMonth));
        $outsideSchedule = ! $expense && (! $recurringExpense->is_active || ! $recurringExpense->villa?->is_active
            || ! $recurringExpense->category->is_active || $period->lt($recurringExpense->starts_on)
            || ($recurringExpense->ends_on && $period->gt($recurringExpense->ends_on)) || $period->lt($recurringExpense->next_period));

        return view('recurring-expenses.additions', [
            'rule' => $recurringExpense, 'month' => $month, 'period' => $period, 'expense' => $expense,
            'pending' => $pending, 'additions' => $additions, 'base' => $base, 'total' => (string) $total,
            'locked' => $locked, 'due' => $due, 'outsideSchedule' => $outsideSchedule,
        ]);
    }

    public function storeAddition(RecurringExpenseAdditionRequest $request, RecurringExpense $recurringExpense)
    {
        $this->service->addMonthlyAddition($recurringExpense, $request->validated(), $request->user());

        return redirect()->route('recurring-expenses.additions', [$recurringExpense, 'month' => $request->validated('month')])
            ->with('success', __('Tambahan disimpan hanya untuk bulan yang dipilih. Nominal rutin tetap.'));
    }

    public function destroyAddition(Request $request, RecurringExpense $recurringExpense, RecurringExpenseAddition $addition)
    {
        $this->authorize('update', $recurringExpense);
        $this->service->removePendingAddition($recurringExpense, $addition, $request->user());

        return redirect()->route('recurring-expenses.additions', [$recurringExpense, 'month' => $addition->period->format('Y-m')])
            ->with('success', __('Tambahan yang belum tercatat sudah dihapus.'));
    }

    private function formData(Request $request, RecurringExpense $rule): array
    {
        return [
            'rule' => $rule,
            'villas' => Villa::accessibleBy($request->user())->active()->orderBy('name')->pluck('name', 'id'),
            'categories' => ExpenseCategory::active()->get()->pluck('name', 'id')->map(fn ($name) => __($name)),
            'methods' => PaymentMethod::options(),
        ];
    }
}
