<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Services\RecurringExpenseService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'propertyme:generate-recurring-expenses';

    protected $description = 'Catat pengeluaran rutin bulanan yang sudah jatuh tempo tanpa duplikasi';

    public function handle(RecurringExpenseService $service): int
    {
        $count = 0;
        $blocked = 0;
        RecurringExpense::where('is_active', true)->eachById(function ($rule) use ($service, &$count, &$blocked) {
            try {
                $count += $service->generate($rule);
                if ($service->blockedPeriods) {
                    $blocked++;
                    $this->warn("Jadwal #{$rule->id}: laporan Final ".implode(', ', $service->blockedPeriods));
                }
            } catch (ValidationException $e) {
                $blocked++;
                $this->warn("Jadwal #{$rule->id}: ".collect($e->errors())->flatten()->implode(' '));
                report($e);
            }
        });
        $this->info("{$count} pengeluaran rutin tercatat; {$blocked} jadwal memerlukan pemeriksaan.");

        return $blocked ? self::FAILURE : self::SUCCESS;
    }
}
