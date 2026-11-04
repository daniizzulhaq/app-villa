<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Riwayat nota milik Staff + status pemeriksaan. Nota yang dikembalikan selalu di atas. */
class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $status = $request->validate(['status' => ['nullable', Rule::enum(ExpenseStatus::class)]])['status'] ?? null;

        $base = Expense::where('created_by', $user->id);
        $counts = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $expenses = (clone $base)
            ->with(['villa:id,name', 'category:id,name,icon', 'receipts:id,expense_id', 'checker:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'returned', 'pending', 'valid')")
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('staff.activity', [
            'expenses' => $expenses,
            'status' => $status,
            'counts' => $counts,
            'monthTotal' => (clone $base)->whereBetween('expense_date', [today()->startOfMonth(), today()->endOfMonth()])->sum('amount'),
        ]);
    }
}
