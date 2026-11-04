<?php

namespace App\Http\Controllers\Manager;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Villa;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Antrean pemeriksaan nota: foto di kiri, data di kanan, Valid / Kembalikan. */
class ReviewController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request): View
    {
        $this->authorize('review', Expense::class);

        $villaId = $request->integer('villa') ?: null;
        $user = $request->user();
        $queue = Expense::query()
            ->tap(fn ($q) => $user->restrictToVillas($q))
            ->where('status', ExpenseStatus::Pending->value)
            ->when($villaId, fn ($q) => $q->where('villa_id', $villaId))
            ->with(['villa:id,name', 'category:id,name,icon', 'creator:id,name', 'receipts'])
            ->orderBy('created_at')
            ->paginate(30)->withQueryString();

        $recent = Expense::query()->tap(fn ($q) => $user->restrictToVillas($q))->whereIn('status', [ExpenseStatus::Valid->value, ExpenseStatus::Returned->value])
            ->whereNotNull('checked_at')->with(['villa:id,name', 'checker:id,name'])
            ->latest('checked_at')->limit(8)->get();

        return view('reviews.index', [
            'queue' => $queue,
            'recent' => $recent,
            'villaId' => $villaId,
            'villas' => Villa::accessibleBy($user)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Expense $expense): View
    {
        $this->authorize('review', Expense::class);
        $this->authorize('view', $expense); // Manager hanya nota di wilayahnya
        $expense->load(['villa', 'category', 'creator:id,name,phone', 'checker:id,name', 'receipts']);

        $pendingIds = Expense::query()->tap(fn ($q) => auth()->user()->restrictToVillas($q))
            ->where('status', ExpenseStatus::Pending->value)->orderBy('created_at')->pluck('id');
        $pos = $pendingIds->search($expense->id);

        return view('reviews.show', [
            'expense' => $expense,
            'position' => $pos === false ? null : $pos + 1,
            'pendingTotal' => $pendingIds->count(),
            'nextId' => $pos === false ? $pendingIds->first() : $pendingIds->get($pos + 1),
            'locked' => ! auth()->user()->can('review', $expense),
        ]);
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('review', $expense);
        $this->expenses->markValid($expense, auth()->user());

        return $this->next($expense, __('Nota ditandai Valid dan masuk perhitungan keuangan.'));
    }

    public function return(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('review', $expense);
        $data = $request->validate(['return_reason' => ['required', 'string', 'min:5', 'max:500']], [
            'return_reason.required' => __('Tulis alasan agar Staff tahu apa yang harus diperbaiki.'),
            'return_reason.min' => __('Alasan terlalu singkat — jelaskan apa yang perlu diperbaiki.'),
        ]);
        $this->expenses->markReturned($expense, auth()->user(), $data['return_reason']);

        return $this->next($expense, __('Nota dikembalikan ke :name beserta alasannya.', ['name' => $expense->creator?->name ?? __('Staff')]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->authorize('review', Expense::class);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => __('Pilih minimal satu nota.')])['ids'];

        $items = Expense::whereIn('id', $ids)->where('status', ExpenseStatus::Pending->value)->get()
            ->filter(fn (Expense $e) => $request->user()->can('review', $e));
        $count = $this->expenses->bulkValid($items, $request->user());

        return back()->with('success', __(':count nota ditandai Valid.', ['count' => $count]));
    }

    private function next(Expense $done, string $message): RedirectResponse
    {
        $next = Expense::query()->tap(fn ($q) => auth()->user()->restrictToVillas($q))
            ->where('status', ExpenseStatus::Pending->value)->whereKeyNot($done->id)->orderBy('created_at')->first();

        return $next
            ? redirect()->route('reviews.show', $next)->with('success', $message.' '.__('Lanjut ke nota berikutnya.'))
            : redirect()->route('reviews.index')->with('success', $message.' '.__('Semua nota sudah diperiksa.'));
    }
}
