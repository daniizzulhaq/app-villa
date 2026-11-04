<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Villa;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Pencarian global: villa, kode booking, tamu, pemasukan, pengeluaran, vendor, kategori, periode.
 * Hasil selalu dibatasi hak akses role (villa yang boleh diakses, Staff tanpa nominal pendapatan).
 */
class SearchController extends Controller
{
    private const MONTHS = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'agu' => 8, 'agt' => 8, 'sep' => 9, 'okt' => 10, 'nov' => 11, 'des' => 12];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->input('q'));
        $groups = [];

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $villaIds = Villa::accessibleBy($user)->pluck('id');
            $staff = $user->isStaff();

            // Periode, mis. "Oktober 2026", "2026-10", "10/2026"
            if ($period = $this->period($q)) {
                $groups[__('Periode')] = [[
                    'title' => $period->translatedFormat('F Y'),
                    'sub' => $staff ? __('Kalender bulan ini') : __('Laporan & dashboard bulan ini'),
                    'url' => $staff ? route('staff.calendar', ['date' => $period->toDateString()]) : route('reports.monthly', ['year' => $period->year, 'month' => $period->month]),
                    'icon' => 'bi-calendar-month',
                ]];
            }

            $groups[__('Villa')] = Villa::accessibleBy($user)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('location', 'like', $like))
                ->limit(6)->get()->map(fn ($v) => [
                    'title' => $v->name, 'sub' => $v->location ?: '—', 'icon' => 'bi-house',
                    'url' => $user->isManager() ? route('villas.show', $v) : ($staff ? route('staff.calendar', ['villa' => $v->id]) : route('dashboard', ['villa' => $v->id])),
                ])->all();

            $groups[__('Reservasi')] = Booking::with(['villa:id,name', 'platform:id,name'])->whereIn('villa_id', $villaIds)
                ->where(fn ($w) => $w->where('booking_ref', 'like', $like)->orWhere('guest_name', 'like', $like))
                ->latest('check_in')->limit(8)->get()->map(fn ($b) => [
                    'title' => ($b->guest_name ?: __('Tamu')).' · '.$b->villa->name,
                    'sub' => $b->platform->name.' · '.($b->booking_ref ?: '—').' · '.$b->check_in->translatedFormat('d M').'–'.$b->check_out->translatedFormat('d M Y'),
                    'icon' => 'bi-calendar-check',
                    'url' => $staff ? route('staff.calendar', ['villa' => $b->villa_id, 'date' => $b->check_in->toDateString()]) : route('bookings.show', $b),
                ])->all();

            if (! $staff) {
                $groups[__('Pemasukan')] = Income::with(['villa:id,name', 'platform:id,name', 'booking:id,guest_name'])->whereIn('villa_id', $villaIds)
                    ->where(fn ($w) => $w->where('booking_ref', 'like', $like)->orWhere('notes', 'like', $like)
                        ->orWhereHas('booking', fn ($b) => $b->where('guest_name', 'like', $like)))
                    ->latest('payment_date')->limit(8)->get()->map(fn ($i) => [
                        'title' => Rupiah::format($i->gross_amount).' · '.$i->villa->name,
                        'sub' => $i->platform->name.' · '.($i->booking_ref ?: '—').' · '.$i->payment_date->translatedFormat('d M Y'),
                        'icon' => 'bi-box-arrow-in-down', 'url' => route('incomes.show', $i),
                    ])->all();
            }

            $groups[__('Pengeluaran')] = ExpenseController::scoped($user)->with(['villa:id,name', 'category:id,name'])
                ->where(fn ($w) => $w->where('description', 'like', $like)->orWhere('vendor', 'like', $like)->orWhere('notes', 'like', $like))
                ->latest('expense_date')->limit(8)->get()->map(fn ($e) => [
                    'title' => $e->description.' · '.Rupiah::format($e->amount),
                    'sub' => ($e->vendor ? $e->vendor.' · ' : '').__($e->category->name).' · '.$e->villa?->name.' · '.$e->expense_date->translatedFormat('d M Y'),
                    'icon' => 'bi-receipt', 'url' => route('expenses.show', $e),
                ])->all();

            $groups[__('Kategori')] = ExpenseCategory::where('name', 'like', $like)->limit(5)->get()->map(fn ($c) => [
                'title' => __($c->name), 'sub' => __('Lihat pengeluaran kategori ini'), 'icon' => $c->icon,
                'url' => $staff ? route('staff.activity') : route('expenses.index', ['category' => $c->id]),
            ])->all();

            $groups = array_filter($groups);
        }

        return view('search.index', ['q' => $q, 'groups' => $groups, 'total' => collect($groups)->flatten(1)->count()]);
    }

    private function period(string $q): ?Carbon
    {
        $s = mb_strtolower($q);
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m)) {
            return checkdate((int) $m[2], 1, (int) $m[1]) ? Carbon::create((int) $m[1], (int) $m[2], 1) : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{4})$#', $s, $m)) {
            return checkdate((int) $m[1], 1, (int) $m[2]) ? Carbon::create((int) $m[2], (int) $m[1], 1) : null;
        }
        if (preg_match('/^([a-z]+)\s+(\d{4})$/', $s, $m) && isset(self::MONTHS[$m[1]])) {
            return Carbon::create((int) $m[2], self::MONTHS[$m[1]], 1);
        }

        return null;
    }
}
