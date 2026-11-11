<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseReturnedNotification;
use App\Notifications\ExpenseSubmittedNotification;
use App\Support\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseService
{
    public const FIELDS = ['villa_id', 'category_id', 'expense_date', 'description', 'vendor', 'amount', 'payment_method', 'notes'];

    public function __construct(private ImageCompressor $images, private ReportPeriodGuard $periods) {}

    /** Staff → "Menunggu pemeriksaan". Manager yang input sendiri → langsung Valid. */
    public function create(array $data, User $actor, array $files): Expense
    {
        $expense = DB::transaction(function () use ($data, $actor, $files) {
            $locked = $this->periods->lockVillas([$data['villa_id']]);
            $this->periods->assertScope((int) $data['villa_id'], $locked);
            $this->periods->assertOpen((int) $data['villa_id'], $data['expense_date'], 'expense_date');
            $isManager = $actor->isManager();
            $expense = Expense::create(Arr::only($data, self::FIELDS) + [
                'status' => $isManager ? ExpenseStatus::Valid : ExpenseStatus::Pending,
                'created_by' => $actor->id,
                'checked_by' => $isManager ? $actor->id : null,
                'checked_at' => $isManager ? now() : null,
            ]);
            $this->storeReceipts($expense, $files);

            return $expense;
        });

        if ($expense->status === ExpenseStatus::Pending) {
            Notify::managers(new ExpenseSubmittedNotification($expense), $expense->villa_id);
        }

        return $expense;
    }

    public function update(Expense $expense, array $data, User $actor, array $files, array $removeIds = []): Expense
    {
        $resubmitted = false;

        $expense = DB::transaction(function () use ($expense, $data, $actor, $files, $removeIds, &$resubmitted) {
            $locked = $this->periods->lockVillas([$expense->villa_id, $data['villa_id'] ?? null,
                Expense::whereKey($expense->id)->value('villa_id')]);
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);
            $this->periods->assertScope($expense->villa_id, $locked);
            $this->periods->assertOpen($expense->villa_id, $expense->expense_date, 'expense_date');
            $this->periods->assertScope((int) ($data['villa_id'] ?? $expense->villa_id), $locked);
            $this->periods->assertOpen((int) ($data['villa_id'] ?? $expense->villa_id), $data['expense_date'] ?? $expense->expense_date, 'expense_date');
            $expense->fill(Arr::only($data, self::FIELDS));

            // Staff memperbaiki nota yang dikembalikan → kirim ulang ke antrean pemeriksaan
            if ($actor->isStaff() && $expense->status === ExpenseStatus::Returned) {
                $expense->status = ExpenseStatus::Pending;
                $resubmitted = true;
            }
            $expense->save();

            foreach ($expense->receipts()->whereIn('id', $removeIds)->get() as $r) {
                Storage::disk('local')->delete($r->path);
                $r->delete();
            }
            $this->storeReceipts($expense, $files);

            return $expense;
        });

        if ($resubmitted) {
            Notify::managers(new ExpenseSubmittedNotification($expense, resubmitted: true), $expense->villa_id);
        }

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $current = $this->writableExpense($expense);
            $current->delete(); // soft delete; file nota tetap disimpan untuk audit
        });
    }

    public function markValid(Expense $expense, User $manager): void
    {
        DB::transaction(function () use ($expense, $manager) {
            $current = $this->writableExpense($expense);
            $current->update([
                'status' => ExpenseStatus::Valid, 'checked_by' => $manager->id,
                'checked_at' => now(), 'return_reason' => null,
            ]);
        });
    }

    public function markReturned(Expense $expense, User $manager, string $reason): void
    {
        $expense = DB::transaction(function () use ($expense, $manager, $reason) {
            $current = $this->writableExpense($expense);
            $current->update([
                'status' => ExpenseStatus::Returned, 'checked_by' => $manager->id,
                'checked_at' => now(), 'return_reason' => $reason,
            ]);

            return $current;
        });

        if ($expense->creator && $expense->creator->isNot($manager)) {
            Notify::send([$expense->creator], new ExpenseReturnedNotification($expense));
        }
    }

    /** @param Collection<int, Expense> $expenses */
    public function bulkValid(Collection $expenses, User $manager): int
    {
        return DB::transaction(function () use ($expenses, $manager) {
            $ids = $expenses->pluck('id');
            $locked = $this->periods->lockVillas(Expense::whereIn('id', $ids)->pluck('villa_id')->all());
            $current = Expense::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()
                ->filter(fn ($expense) => $expense->status === ExpenseStatus::Pending);
            foreach ($current as $expense) {
                $this->periods->assertScope($expense->villa_id, $locked);
                $this->periods->assertOpen($expense->villa_id, $expense->expense_date, 'expense_date');
            }
            $current->each(fn ($expense) => $expense->update([
                'status' => ExpenseStatus::Valid, 'checked_by' => $manager->id,
                'checked_at' => now(), 'return_reason' => null,
            ]));

            return $current->count();
        });
    }

    private function writableExpense(Expense $expense): Expense
    {
        $locked = $this->periods->lockVillas([$expense->villa_id, Expense::whereKey($expense->id)->value('villa_id')]);
        $current = Expense::lockForUpdate()->findOrFail($expense->id);
        $this->periods->assertScope($current->villa_id, $locked);
        $this->periods->assertOpen($current->villa_id, $current->expense_date, 'expense_date');

        return $current;
    }

    public function storeReceipts(Expense $expense, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $dir = 'receipts/'.$expense->villa_id;
            if (str_starts_with((string) $file->getMimeType(), 'image/')) {
                $meta = $this->images->store($file, $dir, 'local', (int) config('propertyme.upload.max_side', 1600));
            } else {
                $path = $file->storeAs($dir.'/'.now()->format('Y/m'), Str::uuid().'.pdf', 'local');
                $meta = ['path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 250, ''), 'size' => $file->getSize()];
            }
            $expense->receipts()->create($meta);
        }
    }
}
