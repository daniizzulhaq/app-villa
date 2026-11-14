<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MonthlyReport;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAddition;
use App\Models\User;
use App\Models\Villa;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringExpenseService
{
    public array $blockedPeriods = [];

    public function __construct(private ReportPeriodGuard $periods) {}

    public function save(array $data, User $actor, ?RecurringExpense $rule = null): RecurringExpense
    {
        return DB::transaction(function () use ($data, $actor, $rule) {
            $locked = $this->periods->lockVillas([$rule?->villa_id, $data['villa_id']]);
            $this->periods->assertScope((int) $data['villa_id'], $locked);
            if ($rule) {
                $rule = RecurringExpense::lockForUpdate()->findOrFail($rule->id);
                $this->periods->assertScope($rule->villa_id, $locked);
                abort_unless($actor->can('update', $rule), 403);
                // Settle overdue months using the old settings before changing future ones.
                $this->generateLocked($rule);
                if (! $rule->is_active) {
                    $villa = Villa::findOrFail($data['villa_id']);
                    $currentMonth = Carbon::parse(Carbon::today($villa->timezone ?: config('app.timezone'))->format('Y-m').'-01');
                    $rule->next_period = max($rule->next_period, $currentMonth);
                }
            } else {
                $rule = new RecurringExpense([
                    'created_by' => $actor->id,
                    'starts_on' => $data['start_month'].'-01',
                    'next_period' => $data['start_month'].'-01',
                ]);
            }
            $rule->fill(collect($data)->except(['start_month', 'end_month'])->all());
            $rule->ends_on = empty($data['end_month']) ? null : Carbon::parse($data['end_month'].'-01')->endOfMonth();
            $rule->save();
            $this->generateLocked($rule);

            return $rule;
        });
    }

    /** Only this explicit operation stops a schedule; historical expenses remain editable. */
    public function delete(RecurringExpense $rule, User $actor): void
    {
        DB::transaction(function () use ($rule, $actor) {
            $locked = $this->periods->lockVillas([$rule->villa_id]);
            $rule = RecurringExpense::lockForUpdate()->findOrFail($rule->id);
            $this->periods->assertScope($rule->villa_id, $locked);
            abort_unless($actor->can('delete', $rule), 403);
            $rule->delete();
        });
    }

    public function generate(RecurringExpense $rule): int
    {
        $this->blockedPeriods = [];

        return DB::transaction(function () use ($rule) {
            $locked = $this->periods->lockVillas([$rule->villa_id]);
            $rule = RecurringExpense::lockForUpdate()->find($rule->id);
            if (! $rule) {
                return 0;
            }
            $this->periods->assertScope($rule->villa_id, $locked);

            return $this->generateLocked($rule, strict: false);
        });
    }

    public function addMonthlyAddition(RecurringExpense $rule, array $data, User $actor): void
    {
        DB::transaction(function () use ($rule, $data, $actor) {
            $period = Carbon::parse($data['month'].'-01');
            $query = Expense::withTrashed()->where('recurring_expense_id', $rule->id)->whereDate('recurring_period', $period);
            $locked = $this->periods->lockVillas([$rule->villa_id, (clone $query)->value('villa_id')]);
            $rule = RecurringExpense::lockForUpdate()->findOrFail($rule->id);
            $this->periods->assertScope($rule->villa_id, $locked);
            abort_unless($actor->can('update', $rule), 403);
            $expense = $query->lockForUpdate()->first();
            $this->periods->assertOpen($rule->villa_id, $period, 'month');
            $addition = ['label' => $data['label'], 'amount' => (string) $data['amount'], 'note' => $data['note'] ?? null];

            if ($expense) {
                if ($expense->trashed()) {
                    throw ValidationException::withMessages(['month' => __('Transaksi bulan ini sudah dihapus. Tambahan tidak dapat dimasukkan.')]);
                }
                $this->periods->assertScope($expense->villa_id, $locked);
                abort_unless($actor->can('update', $expense), 403);
                $this->periods->assertOpen($expense->villa_id, $expense->expense_date, 'month');
                $additions = [...($expense->additions ?? []), $addition];
                $expense->update(['additions' => $additions, 'amount' => $this->totalWithAdditions($expense->base_amount, $additions)]);

                return;
            }

            $villa = Villa::find($rule->villa_id);
            if (! $rule->is_active || ! $villa?->is_active || ! ExpenseCategory::whereKey($rule->category_id)->where('is_active', true)->exists()
                || $period->lt($rule->starts_on) || ($rule->ends_on && $period->gt($rule->ends_on)) || $period->lt($rule->next_period)) {
                throw ValidationException::withMessages(['month' => __('Bulan ini tidak termasuk jadwal rutin yang aktif. Pilih bulan lain atau periksa pengaturan.')]);
            }
            $pending = RecurringExpenseAddition::where('recurring_expense_id', $rule->id)->whereDate('period', $period)
                ->whereNull('applied_expense_id')->get();
            $this->totalWithAdditions($rule->amount, [...$pending->toArray(), $addition]);
            RecurringExpenseAddition::create($addition + [
                'recurring_expense_id' => $rule->id, 'period' => $period, 'created_by' => $actor->id,
            ]);
            // Future entries wait for their due date. Already-due entries are recorded now.
            $this->generateLocked($rule, strict: false);
        });
    }

    public function removePendingAddition(RecurringExpense $rule, RecurringExpenseAddition $addition, User $actor): void
    {
        DB::transaction(function () use ($rule, $addition, $actor) {
            $locked = $this->periods->lockVillas([$rule->villa_id]);
            $rule = RecurringExpense::lockForUpdate()->findOrFail($rule->id);
            $this->periods->assertScope($rule->villa_id, $locked);
            abort_unless($actor->can('update', $rule), 403);
            $addition = RecurringExpenseAddition::where('recurring_expense_id', $rule->id)->lockForUpdate()->findOrFail($addition->id);
            if ($addition->applied_expense_id) {
                throw ValidationException::withMessages(['month' => __('Tambahan sudah masuk transaksi. Edit melalui detail pengeluaran bulan tersebut.')]);
            }
            $this->periods->assertOpen($rule->villa_id, $addition->period, 'month');
            $addition->delete();
        });
    }

    private function totalWithAdditions(string $base, array $additions): string
    {
        if (count($additions) > 30) {
            throw ValidationException::withMessages(['amount' => __('Maksimal 30 tambahan dalam satu bulan.')]);
        }
        $total = BigDecimal::of($base);
        foreach ($additions as $addition) {
            $total = $total->plus((string) $addition['amount']);
        }
        if ($total->isGreaterThan('9999999999999')) {
            throw ValidationException::withMessages(['amount' => __('Total pengeluaran melebihi batas nominal.')]);
        }

        return (string) $total;
    }

    private function generateLocked(RecurringExpense $rule, bool $strict = true): int
    {
        $villa = Villa::find($rule->villa_id);
        if (! $rule->is_active || ! $villa?->is_active || ! ExpenseCategory::whereKey($rule->category_id)->where('is_active', true)->exists()) {
            return 0;
        }

        // Compare local calendar dates, not midnight instants in different timezones.
        $today = Carbon::parse(Carbon::today($villa->timezone ?: config('app.timezone'))->toDateString());
        $count = 0;
        $firstBlocked = null;
        $period = $rule->next_period->copy()->startOfMonth();
        while ($period->lte($today) && (! $rule->ends_on || $period->lte($rule->ends_on))) {
            $due = $period->copy()->day(min($rule->day_of_month, $period->daysInMonth));
            if ($due->gt($today)) {
                break;
            }
            $exists = Expense::withTrashed()->where('recurring_expense_id', $rule->id)
                ->whereDate('recurring_period', $period)->exists();
            if (! $exists) {
                // Keep the cursor at locked periods so an explicit unlock can catch them up.
                if (MonthlyReport::isLocked($rule->villa_id, $due)) {
                    if ($strict) {
                        throw ValidationException::withMessages(['start_month' => __('Pengeluaran rutin belum tercatat karena periode :month sudah Final. Buka kunci laporan terlebih dahulu.', ['month' => $period->format('m/Y')])]);
                    }
                    $firstBlocked ??= $period->copy();
                    $this->blockedPeriods[] = $period->format('m/Y');
                    $period->addMonthNoOverflow();

                    continue;
                }
                $this->periods->assertOpen($rule->villa_id, $due, 'start_month');
                $pending = RecurringExpenseAddition::where('recurring_expense_id', $rule->id)->whereDate('period', $period)
                    ->whereNull('applied_expense_id')->lockForUpdate()->get();
                $additions = $pending->map(fn ($item) => ['label' => $item->label, 'amount' => $item->amount, 'note' => $item->note])->all();
                $expense = Expense::create([
                    'villa_id' => $rule->villa_id, 'category_id' => $rule->category_id,
                    'expense_date' => $due, 'description' => $rule->description, 'vendor' => $rule->vendor,
                    'amount' => $this->totalWithAdditions($rule->amount, $additions), 'base_amount' => $rule->amount, 'additions' => $additions,
                    'payment_method' => $rule->payment_method, 'notes' => $rule->notes,
                    'recurring_expense_id' => $rule->id, 'recurring_period' => $period->toDateString(),
                    'status' => ExpenseStatus::Valid, 'created_by' => $rule->created_by,
                ]);
                foreach ($pending as $item) {
                    $item->update(['applied_expense_id' => $expense->id]);
                }
                $count++;
            }
            $period->addMonthNoOverflow();
        }
        $rule->next_period = $firstBlocked ?? $period;
        $rule->save();

        return $count;
    }
}
