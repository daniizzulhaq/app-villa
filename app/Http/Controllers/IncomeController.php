<?php

namespace App\Http\Controllers;

use App\Enums\FeeBasis;
use App\Http\Requests\IncomeRequest;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\Villa;
use App\Services\Finance\FinanceCalculator;
use App\Services\Finance\FinanceFilter;
use App\Services\IncomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class IncomeController extends Controller
{
    public function __construct(private IncomeService $incomes, private FinanceCalculator $calc) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Income::class);
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'villa' => ['nullable', 'integer'],
            'platform' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $from = Carbon::parse($filters['from'] ?? today()->startOfMonth());
        $to = Carbon::parse($filters['to'] ?? today()->endOfMonth());
        $filters['from'] = $from->toDateString();
        $filters['to'] = $to->toDateString();

        $finance = FinanceFilter::for($user, $from, $to, $filters['villa'] ?? null, $filters['platform'] ?? null);

        $incomes = $this->calc->incomeQuery($finance)
            ->with(['villa:id,name', 'platform:id,name,color', 'booking:id,guest_name,check_in,check_out'])
            ->withCount('attachments')
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('booking_ref', 'like', "%{$s}%")->orWhere('notes', 'like', "%{$s}%")
                ->orWhereHas('booking', fn ($b) => $b->where('guest_name', 'like', "%{$s}%"))))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('incomes.index', [
            'incomes' => $incomes,
            'filters' => $filters,
            'summary' => $this->calc->summary($finance),
            'villas' => Villa::accessibleBy($user)->orderBy('name')->pluck('name', 'id'),
            'platforms' => BookingPlatform::orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Income::class);

        $booking = $request->integer('booking') ? Booking::with('villa')->find($request->integer('booking')) : null;
        $income = new Income([
            'booking_id' => $booking?->id,
            'villa_id' => $booking?->villa_id ?? ($request->integer('villa') ?: null),
            'platform_id' => $booking?->platform_id,
            'booking_ref' => $booking?->booking_ref,
            'gross_amount' => $booking?->total_price,
            'payment_date' => today(),
            'platform_fee' => 0,
            'other_deduction' => 0,
        ]);

        return view('incomes.create', $this->formData($income, $booking));
    }

    public function store(IncomeRequest $request): RedirectResponse
    {
        $income = $this->incomes->create($request->validated(), $request->user(), $request->file('attachments', []));

        return redirect()->route('incomes.show', $income)
            ->with('success', __('Pemasukan tersimpan. Management fee :amount dihitung otomatis.', ['amount' => \App\Support\Rupiah::format($income->fee_amount)]));
    }

    public function show(Income $income): View
    {
        $this->authorize('view', $income);
        $income->load(['villa', 'platform', 'booking', 'attachments', 'creator:id,name']);

        return view('incomes.show', ['income' => $income, 'locked' => \App\Models\MonthlyReport::isLocked($income->villa_id, $income->payment_date)]);
    }

    public function edit(Income $income): View
    {
        $this->authorize('update', $income);
        $income->load(['attachments', 'booking']);

        return view('incomes.edit', $this->formData($income, $income->booking));
    }

    public function update(IncomeRequest $request, Income $income): RedirectResponse
    {
        $this->incomes->update($income, $request->validated(), $request->file('attachments', []), $request->input('remove_attachments', []));

        return redirect()->route('incomes.show', $income)->with('success', __('Pemasukan diperbarui. Semua total & laporan ikut menyesuaikan.'));
    }

    public function destroy(Income $income): RedirectResponse
    {
        $this->authorize('delete', $income);
        $this->incomes->delete($income);

        return redirect()->route('incomes.index')->with('success', __('Pemasukan dihapus. Total & laporan sudah diperbarui.'));
    }

    private function formData(Income $income, ?Booking $booking): array
    {
        $villas = Villa::active()->orderBy('name')->get(['id', 'name', 'fee_percentage', 'fee_basis', 'fee_basis_note']);
        if ($income->villa_id && ! $villas->contains('id', $income->villa_id)) {
            $villas->push(Villa::find($income->villa_id, ['id', 'name', 'fee_percentage', 'fee_basis', 'fee_basis_note']));
        }

        $bookings = Booking::query()->with(['villa:id,name', 'platform:id,name'])
            ->where('check_in', '>=', today()->subMonths(4))
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('check_in')->limit(200)->get()
            ->mapWithKeys(fn (Booking $b) => [$b->id => $b->villa->name.' · '.($b->guest_name ?: __('Perlu dilengkapi')).' · '.$b->check_in->translatedFormat('d M').'–'.$b->check_out->translatedFormat('d M Y').' ('.$b->platform->name.')']);
        if ($booking && ! $bookings->has($booking->id)) {
            $bookings->prepend($booking->villa->name.' · '.($booking->guest_name ?: '—').' · '.$booking->check_in->translatedFormat('d M Y'), $booking->id);
        }

        return [
            'income' => $income,
            'booking' => $booking,
            'bookings' => $bookings,
            'villas' => $villas,
            'villaFees' => $villas->mapWithKeys(fn ($v) => [$v->id => ['pct' => (float) $v->fee_percentage, 'basis' => $v->fee_basis->value, 'label' => $v->feeLabel()]]),
            'platforms' => BookingPlatform::orderBy('sort_order')->pluck('name', 'id'),
            'customBasis' => FeeBasis::Custom->value,
        ];
    }
}
