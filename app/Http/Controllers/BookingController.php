<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Villa;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reservasi untuk Manager (kelola) & Owner (lihat). Staff memakai StaffCalendarController. */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->isStaff(), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'villa' => ['nullable', 'integer'],
            'platform' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $bookings = Booking::query()
            ->whereIn('villa_id', Villa::accessibleBy($user)->select('id'))
            ->with(['villa:id,name', 'platform:id,name,color'])
            ->withSum('incomes', 'gross_amount')
            ->when($filters['villa'] ?? null, fn ($q, $v) => $q->where('villa_id', $v))
            ->when($filters['platform'] ?? null, fn ($q, $v) => $q->where('platform_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('check_out', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('check_in', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('guest_name', 'like', "%{$s}%")->orWhere('booking_ref', 'like', "%{$s}%")))
            ->orderByRaw("status = 'needs_completion' desc")
            ->orderByDesc('check_in')
            ->paginate(20)->withQueryString();

        $needsCompletion = Booking::whereIn('villa_id', Villa::accessibleBy($user)->select('id'))
            ->where('status', BookingStatus::NeedsCompletion->value)->count();

        return view('bookings.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'needsCompletion' => $needsCompletion,
            'villas' => Villa::accessibleBy($user)->orderBy('name')->pluck('name', 'id'),
            'platforms' => BookingPlatform::orderBy('sort_order')->get(),
        ]);
    }

    public function calendar(Request $request): View
    {
        abort_if($request->user()->isStaff(), 403);

        return view('bookings.calendar', [
            'villas' => Villa::accessibleBy($request->user())->active()->orderBy('name')->pluck('name', 'id'),
            'platforms' => BookingPlatform::orderBy('sort_order')->get(),
            'selectedVilla' => $request->integer('villa') ?: null,
        ]);
    }

    /** JSON untuk FullCalendar — dipakai semua role. Tidak pernah berisi nominal uang. */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'villa' => ['nullable', 'integer'],
            'cancelled' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $bookings = Booking::query()
            ->whereIn('villa_id', Villa::accessibleBy($user)->select('id'))
            ->with(['villa:id,name', 'platform:id,name,color'])
            ->when($data['villa'] ?? null, fn ($q, $v) => $q->where('villa_id', $v))
            ->when(! ($data['cancelled'] ?? false), fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled->value))
            ->where('check_in', '<', Carbon::parse($data['end'])->toDateString())
            ->where('check_out', '>', Carbon::parse($data['start'])->toDateString())
            ->get();

        $events = [];
        foreach ($bookings as $b) {
            $title = $b->guest_name ?: ($b->status === BookingStatus::NeedsCompletion ? __('Perlu dilengkapi') : __('Tamu'));
            if (empty($data['villa'])) {
                $title = $b->villa->name.' · '.$title;
            }
            $events[] = [
                'id' => (string) $b->id,
                'title' => $title,
                'start' => $b->check_in->toDateString(),
                'end' => $b->check_out->toDateString(), // eksklusif = malam terakhir check_out-1
                'allDay' => true,
                'backgroundColor' => $b->status === BookingStatus::Cancelled ? '#cbd5e1' : $b->platform->color,
                'borderColor' => $b->platform->color,
                'textColor' => '#ffffff',
                'classNames' => ['pm-ev', 'pm-ev--'.$b->status->value],
                'extendedProps' => [
                    'villa' => $b->villa->name,
                    'platform' => $b->platform->name,
                    'status' => $b->status->label(),
                    'statusBadge' => $b->status->badge(),
                    'guest' => $b->guest_name,
                    'guests' => $b->guest_count,
                    'arrival' => $b->arrival_time ? substr($b->arrival_time, 0, 5) : null,
                    'notes' => $b->notes,
                    'ref' => $b->booking_ref,
                    'checkIn' => $b->check_in->translatedFormat('D, d M Y'),
                    'checkOut' => $b->check_out->translatedFormat('D, d M Y'),
                    'nights' => $b->nights(),
                    'url' => $user->isStaff() ? null : route('bookings.show', $b),
                ],
            ];
            // Latar tanggal terisi (beda warna dengan tanggal kosong) saat satu villa dipilih
            if (! empty($data['villa']) && $b->status !== BookingStatus::Cancelled) {
                $events[] = [
                    'start' => $b->check_in->toDateString(), 'end' => $b->check_out->toDateString(),
                    'display' => 'background', 'backgroundColor' => $b->platform->color, 'classNames' => ['pm-occupied'],
                ];
            }
        }

        return response()->json($events);
    }

    public function show(Request $request, Booking $booking): View
    {
        $this->authorize('view', $booking);
        abort_if($request->user()->isStaff(), 403);

        $booking->load(['villa', 'platform', 'creator:id,name', 'incomes' => fn ($q) => $q->orderBy('payment_date')]);

        return view('bookings.show', compact('booking'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Booking::class);

        $booking = new Booking([
            'villa_id' => $request->integer('villa') ?: null,
            'platform_id' => BookingPlatform::where('slug', BookingPlatform::DIRECT)->value('id'),
            'check_in' => $request->date('date') ?? today(),
            'check_out' => ($request->date('date') ?? today())->copy()->addDay(),
            'status' => BookingStatus::Confirmed,
            'guest_count' => 2,
        ]);

        return view('bookings.create', $this->formData($booking));
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        $booking = $this->bookings->create($request->validated(), $request->user());

        return redirect()->route('bookings.show', $booking)->with('success', __('Reservasi tersimpan dan kalender diperbarui.'));
    }

    public function edit(Booking $booking): View
    {
        $this->authorize('update', $booking);

        return view('bookings.edit', $this->formData($booking));
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->bookings->update($booking, $request->validated());

        return redirect()->route('bookings.show', $booking)->with('success', __('Reservasi diperbarui.'));
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->authorize('delete', $booking);

        if ($booking->incomes()->exists()) {
            return back()->with('error', __('Reservasi ini sudah memiliki pemasukan. Ubah status menjadi Cancelled, atau hapus pemasukannya dulu.'));
        }
        $booking->delete();

        return redirect()->route('bookings.index')->with('success', __('Reservasi dihapus.'));
    }

    private function formData(Booking $booking): array
    {
        return [
            'booking' => $booking,
            'villas' => Villa::active()->orderBy('name')->pluck('name', 'id'),
            'platforms' => BookingPlatform::orderBy('sort_order')->pluck('name', 'id'),
            'statuses' => collect(BookingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
        ];
    }
}
