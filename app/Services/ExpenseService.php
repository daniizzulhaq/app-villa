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

    public function __construct(private ImageCompressor $images) {}

    /** Staff → "Menunggu pemeriksaan". Manager yang input sendiri → langsung Valid. */
    public function create(array $data, User $actor, array $files): Expense
    {
        $expense = DB::transaction(function () use ($data, $actor, $files) {
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

        DB::transaction(function () use ($expense, $data, $actor, $files, $removeIds, &$resubmitted) {
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
        });

        if ($resubmitted) {
            Notify::managers(new ExpenseSubmittedNotification($expense, resubmitted: true), $expense->villa_id);
        }

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $expense->delete(); // soft delete; file nota tetap disimpan untuk audit
    }

    public function markValid(Expense $expense, User $manager): void
    {
        $expense->update([
            'status' => ExpenseStatus::Valid,
            'checked_by' => $manager->id,
            'checked_at' => now(),
            'return_reason' => null,
        ]);
    }

    public function markReturned(Expense $expense, User $manager, string $reason): void
    {
        $expense->update([
            'status' => ExpenseStatus::Returned,
            'checked_by' => $manager->id,
            'checked_at' => now(),
            'return_reason' => $reason,
        ]);

        if ($expense->creator && $expense->creator->isNot($manager)) {
            Notify::send([$expense->creator], new ExpenseReturnedNotification($expense));
        }
    }

    /** @param Collection<int, Expense> $expenses */
    public function bulkValid(Collection $expenses, User $manager): int
    {
        return DB::transaction(function () use ($expenses, $manager) {
            $expenses->each(fn (Expense $e) => $this->markValid($e, $manager));

            return $expenses->count();
        });
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
