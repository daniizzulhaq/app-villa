<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseReceipt;
use App\Models\User;
use App\Models\Villa;
use App\Services\ExpenseService;
use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    /** Query dasar sesuai role: Manager semua, Owner hanya Valid villanya, Staff hanya miliknya. */
    public static function scoped(User $user): Builder
    {
        return Expense::query()
            ->whereIn('villa_id', Villa::accessibleBy($user)->withTrashed()->select('id'))
            ->when($user->isOwner(), fn ($q) => $q->where('status', ExpenseStatus::Valid->value))
            ->when($user->isStaff(), fn ($q) => $q->where('created_by', $user->id));
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'villa' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ExpenseStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
        ]);

        $query = self::scoped($user)
            ->when($filters['villa'] ?? null, fn ($q, $v) => $q->where('villa_id', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(($filters['status'] ?? null) && ! $user->isOwner(), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('expense_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('expense_date', '<=', $v))
            ->when($filters['min'] ?? null, fn ($q, $v) => $q->where('amount', '>=', $v))
            ->when($filters['max'] ?? null, fn ($q, $v) => $q->where('amount', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('description', 'like', "%{$s}%")->orWhere('vendor', 'like', "%{$s}%")->orWhere('notes', 'like', "%{$s}%")));

        $total = (clone $query)->sum('amount');
        $expenses = $query->with(['villa:id,name', 'category:id,name,icon', 'creator:id,name', 'receipts:id,expense_id'])
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'total' => $total,
            'villas' => Villa::accessibleBy($user)->orderBy('name')->pluck('name', 'id'),
            'categories' => ExpenseCategory::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->map(fn ($n) => __($n)),
        ]);
    }

    /** Form mobile-first. Villa terakhir dipakai & tanggal hari ini sudah terisi. */
    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);
        $user = $request->user();

        $villas = Villa::accessibleBy($user)->active()->orderBy('name')->get(['id', 'name']);
        $lastVillaId = Expense::where('created_by', $user->id)->latest('id')->value('villa_id');
        $villaId = $request->integer('villa') ?: ($villas->contains('id', $lastVillaId) ? $lastVillaId : $villas->first()?->id);

        $expense = new Expense([
            'villa_id' => $villaId,
            'expense_date' => today(),
            'payment_method' => PaymentMethod::Cash,
        ]);

        return view('expenses.create', $this->formData($expense, $villas));
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->user(), $request->file('receipts', []));

        $msg = $expense->status === ExpenseStatus::Valid
            ? __('Pengeluaran :amount tersimpan dan langsung Valid.', ['amount' => Rupiah::format($expense->amount)])
            : __('Nota :amount terkirim. Status: Menunggu pemeriksaan Manager.', ['amount' => Rupiah::format($expense->amount)]);

        if ($request->input('after') === 'another') {
            return redirect()->route('expenses.create', ['villa' => $expense->villa_id])->with('success', $msg);
        }

        return redirect()->route($request->user()->isStaff() ? 'staff.activity' : 'expenses.show', $request->user()->isStaff() ? [] : $expense)
            ->with('success', $msg);
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);
        $expense->load(['villa', 'category', 'creator:id,name', 'checker:id,name', 'receipts']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->authorize('update', $expense);
        $expense->load('receipts');

        $villas = Villa::accessibleBy($request->user())->orderBy('name')->get(['id', 'name']);

        return view('expenses.edit', $this->formData($expense, $villas));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $wasReturned = $expense->status === ExpenseStatus::Returned;
        $this->expenses->update($expense, $request->validated(), $request->user(), $request->file('receipts', []), $request->input('remove_receipts', []));

        $msg = $wasReturned && $request->user()->isStaff()
            ? __('Nota diperbaiki dan dikirim ulang ke Manager.')
            : __('Pengeluaran diperbarui. Total & laporan ikut menyesuaikan.');

        return redirect()->route($request->user()->isStaff() ? 'staff.activity' : 'expenses.show', $request->user()->isStaff() ? [] : $expense)->with('success', $msg);
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);
        $this->expenses->delete($expense);

        return redirect()->route($request->user()->isStaff() ? 'staff.activity' : 'expenses.index')->with('success', __('Pengeluaran dihapus.'));
    }

    /** Galeri nota per villa per bulan. */
    public function gallery(Request $request): View
    {
        $user = $request->user();
        abort_if($user->isStaff(), 403);

        $villas = Villa::accessibleBy($user)->orderBy('name')->pluck('name', 'id');
        $villaId = $request->integer('villa') ?: $villas->keys()->first();
        abort_if($villaId && ! $villas->has($villaId), 403);
        $month = Carbon::parse($request->input('month', today()->format('Y-m')).'-01');

        $expenses = self::scoped($user)
            ->where('villa_id', $villaId)
            ->whereBetween('expense_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->with(['category:id,name', 'receipts'])
            ->orderBy('expense_date')->get();

        return view('expenses.gallery', [
            'villas' => $villas, 'villaId' => $villaId, 'month' => $month, 'expenses' => $expenses,
            'receiptCount' => $expenses->sum(fn ($e) => $e->receipts->count()),
        ]);
    }

    private function formData(Expense $expense, $villas): array
    {
        return [
            'expense' => $expense,
            'villas' => $villas,
            'categories' => ExpenseCategory::active()->get(),
            'methods' => PaymentMethod::options(),
            'maxFiles' => (int) config('propertyme.upload.max_files', 6),
        ];
    }
}
