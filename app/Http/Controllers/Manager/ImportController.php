<?php

namespace App\Http\Controllers\Manager;

use App\Exports\Sheet;
use App\Http\Controllers\Controller;
use App\Models\BookingPlatform;
use App\Models\ImportBatch;
use App\Models\Income;
use App\Models\Villa;
use App\Services\PayoutImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ImportController extends Controller
{
    public function __construct(private PayoutImportService $importer) {}

    public function create(): View
    {
        $this->authorize('import', Income::class);

        return view('imports.create', [
            'villas' => Villa::active()->accessibleBy(auth()->user())->orderBy('name')->pluck('name', 'id'),
            'platforms' => BookingPlatform::orderBy('sort_order')->pluck('name', 'id'),
            'batches' => ImportBatch::with('creator:id,name')->latest()->limit(10)->get(),
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $this->authorize('import', Income::class);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'villa_id' => ['nullable', 'integer', 'exists:villas,id', function ($attr, $value, $fail) use ($request) {
                if (! $request->user()->canAccessVilla((int) $value)) {
                    $fail(__('Anda hanya dapat memilih villa di wilayah Anda.'));
                }
            }],
            'platform_id' => ['nullable', 'integer', 'exists:booking_platforms,id'],
            'date_format' => ['required', 'in:d/m/Y,m/d/Y'],
        ], ['file.mimes' => __('File harus CSV atau Excel (.xlsx/.xls).'), 'file.required' => __('Pilih file payout yang akan diimport.')], ['file' => 'file']);

        try {
            $preview = $this->importer->preview($request->file('file'), $data['villa_id'] ?? null, $data['platform_id'] ?? null, $data['date_format'], $request->user()->accessibleVillaIds());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('File tidak bisa dibaca. Pastikan baris pertama berisi nama kolom, atau gunakan template.'));
        }

        if (! $preview['rows']) {
            return back()->withInput()->with('error', __('Tidak ada baris data yang bisa dibaca dari file ini.'));
        }

        $token = (string) Str::uuid();
        Cache::put("import:{$request->user()->id}:{$token}", $preview + ['file_name' => $request->file('file')->getClientOriginalName()], now()->addHour());

        return view('imports.preview', ['preview' => $preview, 'token' => $token, 'fileName' => $request->file('file')->getClientOriginalName()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('import', Income::class);
        $token = $request->validate(['token' => ['required', 'uuid']])['token'];
        $key = "import:{$request->user()->id}:{$token}";
        $preview = Cache::pull($key);

        if (! $preview) {
            return redirect()->route('imports.create')->with('error', __('Pratinjau kedaluwarsa. Unggah ulang file.'));
        }

        $batch = $this->importer->import($preview, $preview['file_name'], $request->user());

        return redirect()->route('imports.create')->with('success',
            __('Import selesai: :imported pemasukan disimpan, :duplicate duplikat dilewati, :failed baris error dilewati.', ['imported' => $batch->rows_imported, 'duplicate' => $batch->rows_duplicate, 'failed' => $batch->rows_failed])
            .($batch->bookings_priced ? ' '.__(':count harga reservasi diperbarui.', ['count' => $batch->bookings_priced]) : ''));
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Income::class);
        $villa = Villa::active()->accessibleBy(auth()->user())->orderBy('name')->value('name') ?? 'Nama Villa';

        return Excel::download(new Sheet('Template', [
            'villa', 'platform', 'kode_booking', 'tanggal_bayar', 'gross', 'komisi', 'potongan_lain', 'sudah_net', 'nama_tamu', 'catatan',
        ], [
            [$villa, 'Airbnb', 'HMABC12345', today()->format('Y-m-d'), 8000000, 1200000, 0, 'tidak', 'John Smith', 'Contoh baris — hapus sebelum import'],
            [$villa, 'Booking.com', '4012345678', today()->format('Y-m-d'), 6500000, 975000, 50000, 'tidak', 'Ayu', ''],
        ]), 'template-import-payout-propertyme.xlsx');
    }
}
