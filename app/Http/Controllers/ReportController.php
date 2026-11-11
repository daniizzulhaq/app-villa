<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\MonthlyReport;
use App\Models\Villa;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /** Matriks villa × bulan dengan status Draft/Final. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MonthlyReport::class);
        $user = $request->user();
        $year = $request->integer('year') ?: today()->year;

        $villas = Villa::accessibleBy($user)->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active']);
        $reports = MonthlyReport::whereIn('villa_id', $villas->pluck('id'))->where('year', $year)->get()
            ->groupBy('villa_id')->map(fn ($g) => $g->keyBy('month'));

        return view('reports.index', compact('villas', 'reports', 'year'));
    }

    public function monthly(Request $request): View
    {
        [$villaId, $year, $month] = $this->params($request, true);
        $from = Carbon::create($year, $month, 1);
        $data = $this->reports->build($request->user(), $villaId, $from, $from->copy()->endOfMonth(), 'monthly');

        if ($villaId && $request->user()->isManager() && $data['villa']) {
            $data['blockers'] = $this->reports->blockers($data['villa'], $year, $month);
            $data['warnings'] = $this->reports->warnings($data['villa'], $year, $month);
        }

        return view('reports.show', $data + ['year' => $year, 'month' => $month, 'villaId' => $villaId, 'villaOptions' => $this->villaOptions($request)]);
    }

    public function annual(Request $request): View
    {
        [$villaId, $year] = $this->params($request, false);
        $from = Carbon::create($year, 1, 1);
        $data = $this->reports->build($request->user(), $villaId, $from, $from->copy()->endOfYear(), 'annual');

        return view('reports.show', $data + ['year' => $year, 'month' => null, 'villaId' => $villaId, 'villaOptions' => $this->villaOptions($request)]);
    }

    public function pdf(Request $request): Response
    {
        // Font PDF (DejaVu Sans) tidak memuat huruf Mandarin/Korea/Jepang → PDF untuk bahasa itu dibuat dalam bahasa Inggris.
        $original = app()->getLocale();
        if (in_array($original, ['zh_CN', 'ko', 'ja'], true)) {
            app()->setLocale('en');
            Carbon::setLocale('en');
        }

        try {
            $data = $this->dataFor($request);
            $pdf = Pdf::loadView('reports.pdf', $data)->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $pdf->render();
            $pageLabel = __('Page :page of :pages');
            $pdf->getDomPDF()->getCanvas()->page_script(static function ($number, $count, $canvas, $fonts) use ($pageLabel) {
                $font = $fonts->getFont('DejaVu Sans', 'normal');
                $text = strtr($pageLabel, [':page' => (string) $number, ':pages' => (string) $count]);
                $width = $fonts->getTextWidth($text, $font, 6.5);
                $canvas->text($canvas->get_width() - 36 - $width, $canvas->get_height() - 37, $text, $font, 6.5, [0.44, 0.44, 0.44]);
            });

            return $pdf->download($this->fileName($data, 'pdf'));
        } finally {
            app()->setLocale($original);
            Carbon::setLocale($original);
        }
    }

    public function excel(Request $request): Response
    {
        $data = $this->dataFor($request);

        return Excel::download(new ReportExport($data), $this->fileName($data, 'xlsx'));
    }

    public function finalize(Request $request): RedirectResponse
    {
        $data = $request->validate(['villa' => ['required', 'integer', 'exists:villas,id'], 'year' => ['required', 'integer'], 'month' => ['required', 'integer', 'between:1,12']]);
        $villa = Villa::findOrFail($data['villa']);
        $existing = MonthlyReport::firstOrNew(['villa_id' => $villa->id, 'year' => $data['year'], 'month' => $data['month']]);
        $this->authorize('finalize', $existing);

        $this->reports->finalize($villa, $data['year'], $data['month'], $request->user());

        return back()->with('success', ($villa->owner ? __('Laporan ditandai Final. Bulan ini terkunci dan Owner sudah diberi tahu.') : __('Laporan ditandai Final. Bulan ini terkunci.')));
    }

    public function unlock(Request $request, MonthlyReport $report): RedirectResponse
    {
        $this->authorize('unlock', $report);
        $data = $request->validate(['unlock_reason' => ['required', 'string', 'min:5', 'max:255']], [
            'unlock_reason.required' => __('Tulis alasan membuka kunci — tercatat di audit log.'),
        ]);
        $this->reports->unlock($report, $request->user(), $data['unlock_reason']);

        return back()->with('warning', __('Kunci bulan dibuka. Transaksi bisa diubah; tandai Final lagi setelah selesai.'));
    }

    /* ------------------------------------------------------------------ */

    private function dataFor(Request $request): array
    {
        $type = $request->input('type') === 'annual' ? 'annual' : 'monthly';
        [$villaId, $year, $month] = $this->params($request, $type === 'monthly');
        $from = $type === 'annual' ? Carbon::create($year, 1, 1) : Carbon::create($year, $month, 1);
        $to = $type === 'annual' ? $from->copy()->endOfYear() : $from->copy()->endOfMonth();

        return $this->reports->build($request->user(), $villaId, $from, $to, $type);
    }

    private function params(Request $request, bool $withMonth): array
    {
        $this->authorize('viewAny', MonthlyReport::class);
        $v = $request->validate([
            'villa' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $villaId = $v['villa'] ?? null;
        abort_if($villaId && ! $request->user()->canAccessVilla($villaId), 403);

        $default = today()->subMonth();

        return [$villaId, $v['year'] ?? ($withMonth ? $default->year : today()->year), $withMonth ? ($v['month'] ?? $default->month) : null];
    }

    private function villaOptions(Request $request)
    {
        return Villa::accessibleBy($request->user())->orderBy('name')->pluck('name', 'id');
    }

    private function fileName(array $data, string $ext): string
    {
        $who = $data['villa'] ? Str::slug($data['villa']->name) : 'semua-villa';
        $period = $data['type'] === 'annual' ? $data['from']->format('Y') : $data['from']->format('Y-m');

        return "laporan-{$who}-{$period}.{$ext}";
    }
}
