<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\FeeBasis;
use App\Imports\HeadingImport;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\ImportBatch;
use App\Models\Income;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import payout CSV/Excel (template PropertyMe, export Airbnb, atau Booking.com).
 * Alur: unggah → pratinjau (baris OK / DUPLIKAT / ERROR) → konfirmasi → simpan.
 * Duplikat = villa + platform + kode booking sudah ada (di database atau dua kali di file).
 *
 * Tiap baris dicocokkan ke reservasi (kode booking, atau villa + platform + tanggal check-in/out —
 * penting untuk Booking.com yang iCal-nya tanpa kode). Reservasi yang cocok ikut dilengkapi:
 * harga reservasi (gross), kode booking, dan nama tamu. Harga yang diisi manual tidak ditimpa.
 */
class PayoutImportService
{
    /** Nama kolom yang dikenali (sudah di-slug oleh WithHeadingRow). */
    private const ALIASES = [
        'villa' => ['villa', 'nama_villa', 'listing', 'property', 'properti', 'property_name'],
        'platform' => ['platform', 'sumber', 'source', 'channel'],
        'booking_ref' => ['kode_booking', 'booking_ref', 'confirmation_code', 'reservation_number', 'booking_number', 'book_number', 'kode', 'reference', 'reservation_id'],
        'payment_date' => ['tanggal_bayar', 'payment_date', 'payout_date', 'tanggal', 'date', 'paid_out_date', 'payment_date_utc'],
        'gross' => ['gross', 'gross_amount', 'gross_earnings', 'gross_booking_income', 'total', 'price', 'original_amount'],
        'platform_fee' => ['komisi', 'komisi_platform', 'platform_fee', 'service_fee', 'host_fee', 'commission', 'commission_amount'],
        'other' => ['potongan_lain', 'other_deduction', 'potongan', 'pajak', 'tax', 'occupancy_taxes'],
        'amount' => ['amount', 'paid_out', 'payout', 'net_amount', 'nominal'],
        'is_net' => ['sudah_net', 'is_net', 'is_net_of_platform'],
        'guest' => ['nama_tamu', 'guest', 'guest_name', 'guest_names', 'booker_name', 'booked_by'],
        'check_in' => ['check_in', 'tanggal_check_in', 'checkin', 'start_date', 'arrival'],
        'check_out' => ['check_out', 'tanggal_check_out', 'checkout', 'end_date', 'departure'],
        'nights' => ['nights', 'malam', 'jumlah_malam'],
        'type' => ['type', 'jenis'],
        'notes' => ['catatan', 'notes', 'keterangan', 'details'],
    ];

    public function __construct(private IncomeService $incomes) {}

    /** @return array{rows: array, columns: array, counts: array} */
    /** @param  array<int>|null  $allowedVillaIds  Manager wilayah: hanya villa wilayahnya (villa lain dianggap tidak ditemukan) */
    public function preview(UploadedFile $file, ?int $defaultVillaId, ?int $defaultPlatformId, string $dateFormat, ?array $allowedVillaIds = null): array
    {
        $sheet = Excel::toArray(new HeadingImport, $file)[0] ?? [];
        $columns = $sheet ? array_keys($sheet[0]) : [];
        $map = $this->mapColumns($columns);

        $villas = Villa::query()->when($allowedVillaIds !== null, fn ($q) => $q->whereIn('id', $allowedVillaIds ?: [0]))
            ->get(['id', 'name', 'fee_basis']);
        $platforms = BookingPlatform::all(['id', 'name', 'slug']);
        // Export reservasi Booking.com tidak punya kolom tanggal bayar → pakai tanggal check-out.
        $dateFromCheckout = ! isset($map['payment_date']) && (isset($map['check_out']) || isset($map['check_in'], $map['nights']));
        $seen = [];
        $rows = [];

        foreach ($sheet as $i => $raw) {
            if (! array_filter($raw, fn ($v) => $v !== null && $v !== '')) {
                continue; // baris kosong
            }
            $get = fn ($k) => isset($map[$k]) ? ($raw[$map[$k]] ?? null) : null;

            // Airbnb: baris "Payout" adalah ringkasan transfer, bukan reservasi
            if (in_array(strtolower(trim((string) $get('type'))), ['payout', 'adjustment', 'resolution adjustment'], true)) {
                continue;
            }

            $errors = [];
            $villa = $this->matchVilla($get('villa'), $villas) ?? ($defaultVillaId ? $villas->firstWhere('id', $defaultVillaId) : null);
            $platform = $this->matchPlatform($get('platform'), $platforms) ?? ($defaultPlatformId ? $platforms->firstWhere('id', $defaultPlatformId) : null);
            $checkIn = $this->parseDate($get('check_in'), $dateFormat);
            $checkOut = $this->parseDate($get('check_out'), $dateFormat);
            if (! $checkOut && $checkIn && (int) $get('nights') > 0) {
                $checkOut = Carbon::parse($checkIn)->addDays((int) $get('nights'))->toDateString();
            }
            $date = $this->parseDate($get('payment_date'), $dateFormat) ?? ($dateFromCheckout ? $checkOut : null);
            $gross = $this->parseMoney($get('gross'));
            $isNet = $this->parseBool($get('is_net'));
            if ($gross === null && ($amount = $this->parseMoney($get('amount'))) !== null) {
                $gross = $amount; // hanya ada nominal payout → anggap sudah net
                $isNet = true;
            }
            $fee = $this->parseMoney($get('platform_fee')) ?? '0';
            $other = $this->parseMoney($get('other')) ?? '0';
            $ref = trim((string) $get('booking_ref')) ?: null;

            if (! $villa) {
                $errors[] = $get('villa') ? __('Villa ":name" tidak ditemukan', ['name' => $get('villa')]) : __('Villa kosong');
            }
            if (! $platform) {
                $errors[] = __('Platform tidak dikenali');
            }
            if (! $date) {
                $errors[] = $get('payment_date') ? __('Tanggal tidak valid: :date', ['date' => $get('payment_date')]) : __('Tanggal kosong');
            }
            if ($gross === null || bccomp($gross, '0', 2) <= 0) {
                $errors[] = __('Nominal gross kosong / tidak valid');
            }
            if ($fee !== null && str_starts_with($fee, '-')) {
                $fee = ltrim($fee, '-'); // beberapa export menulis komisi sebagai negatif
            }
            if ($villa && $villa->fee_basis === FeeBasis::Custom) {
                $errors[] = __('Villa memakai basis fee custom — catat manual');
            }
            if ($villa && $date && MonthlyReport::isLocked($villa->id, $date)) {
                $errors[] = __('Bulan sudah Final (terkunci)');
            }

            $booking = $villa ? $this->findBooking($villa->id, $platform?->id, $ref, $checkIn, $checkOut) : null;
            if (! $booking && $villa && $checkIn && $checkOut && Booking::where('villa_id', $villa->id)
                ->when($platform, fn ($q) => $q->where('platform_id', $platform->id))
                ->whereDate('check_in', $checkIn)->whereDate('check_out', $checkOut)
                ->whereHas('incomes', fn ($q) => $q->whereNotNull('hostex_stay_code'))->exists()) {
                $errors[] = __('Automatic income matches these stay dates. Check the booking reference.');
            }

            $status = $errors ? 'error' : 'ok';
            $dupReason = null;
            $automaticIncome = $booking && Income::where('booking_id', $booking->id)->whereNotNull('hostex_stay_code')->exists();
            if (! $errors && $automaticIncome) {
                $status = 'duplicate';
                $dupReason = __('This booking already has automatic Hostex income. Open the existing entry.');
            }
            if (! $errors && $ref) {
                $key = $villa->id.'|'.$platform->id.'|'.mb_strtolower($ref);
                if (isset($seen[$key])) {
                    $status = 'duplicate';
                    $dupReason = __('Dobel di file (baris :row)', ['row' => $seen[$key]]);
                } elseif (Income::where('villa_id', $villa->id)->where('platform_id', $platform->id)->where('booking_ref', $ref)->exists()) {
                    $status = 'duplicate';
                    $dupReason = __('Sudah tercatat sebelumnya');
                }
                $seen[$key] ??= $i + 2;
            }

            $rows[] = [
                'line' => $i + 2, // +1 header, +1 basis-1
                'status' => $status,
                'errors' => $errors,
                'dup_reason' => $dupReason,
                'villa_id' => $villa?->id,
                'villa' => $villa?->name ?? (string) $get('villa'),
                'platform_id' => $platform?->id,
                'platform' => $platform?->name,
                'booking_ref' => $ref,
                'payment_date' => $date,
                'gross_amount' => $gross,
                'platform_fee' => $fee,
                'other_deduction' => $other,
                'is_net_of_platform' => $isNet,
                'guest' => trim((string) $get('guest')) ?: null,
                'notes' => trim((string) $get('notes')) ?: null,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'booking_id' => $booking?->id,
                'booking_label' => $booking ? ($booking->guest_name ?: __('Tamu belum diisi')).' · '.$booking->check_in->translatedFormat('d M').' – '.$booking->check_out->translatedFormat('d M Y') : null,
                'sets_price' => $booking && ! $automaticIncome && ! $errors && $this->priceChanges($booking, $gross, $isNet),
            ];
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
            'mapped' => $map,
            'counts' => collect($rows)->countBy('status')->all() + ['ok' => 0, 'duplicate' => 0, 'error' => 0],
            'prices' => collect($rows)->where('sets_price', true)->unique('booking_id')->count(),
        ];
    }

    /** Simpan baris OK. Baris duplikat & error dilewati (duplikat tetap boleh melengkapi data reservasi). */
    public function import(array $preview, string $fileName, User $actor): ImportBatch
    {
        return DB::transaction(function () use ($preview, $fileName, $actor) {
            $batch = ImportBatch::create([
                'type' => 'income', 'file_name' => Str::limit($fileName, 250, ''), 'rows_total' => count($preview['rows']),
                'rows_duplicate' => $preview['counts']['duplicate'] ?? 0, 'rows_failed' => $preview['counts']['error'] ?? 0,
                'created_by' => $actor->id,
            ]);
            $imported = 0;
            $priced = [];

            foreach ($preview['rows'] as $r) {
                if ($r['status'] === 'error') {
                    continue;
                }
                $booking = ! empty($r['booking_id']) ? Booking::find($r['booking_id']) : null;
                $automaticIncome = $booking && Income::where('booking_id', $booking->id)->whereNotNull('hostex_stay_code')->exists();
                if ($booking && ! $automaticIncome && $this->completeBooking($booking, $r)) {
                    $priced[$booking->id] = true;
                }
                if ($r['status'] !== 'ok') {
                    continue;
                }
                if ($automaticIncome) {
                    $batch->increment('rows_duplicate');

                    continue;
                }
                // Cek ulang duplikat (bisa saja ada yang menyimpan di sela pratinjau & konfirmasi)
                if ($r['booking_ref'] && Income::where('villa_id', $r['villa_id'])->where('platform_id', $r['platform_id'])->where('booking_ref', $r['booking_ref'])->exists()) {
                    $batch->increment('rows_duplicate');

                    continue;
                }
                $this->incomes->create([
                    'booking_id' => $booking?->id,
                    'villa_id' => $r['villa_id'],
                    'platform_id' => $r['platform_id'],
                    'booking_ref' => $r['booking_ref'],
                    'payment_date' => $r['payment_date'],
                    'gross_amount' => $r['gross_amount'],
                    'platform_fee' => $r['platform_fee'],
                    'other_deduction' => $r['other_deduction'],
                    'is_net_of_platform' => $r['is_net_of_platform'],
                    'notes' => trim(($r['guest'] ? __('Tamu: :name', ['name' => $r['guest']]).'. ' : '').($r['notes'] ?? '')) ?: null,
                    'import_batch_id' => $batch->id,
                ], $actor);
                $imported++;
            }

            $batch->update(['rows_imported' => $imported]);
            $batch->setAttribute('bookings_priced', count($priced)); // hanya untuk pesan sukses

            return $batch;
        });
    }

    /** Cari reservasi untuk baris payout: kode booking dulu, lalu villa + platform + tanggal menginap. */
    public function findBooking(int $villaId, ?int $platformId, ?string $ref, ?string $checkIn, ?string $checkOut): ?Booking
    {
        if ($ref && ($byRef = Booking::where('villa_id', $villaId)->when($platformId, fn ($q) => $q->where('platform_id', $platformId))->where('booking_ref', $ref)->first())) {
            return $byRef;
        }
        if (! $checkIn) {
            return null;
        }
        if (! $ref && $checkOut) {
            $automatic = Booking::where('villa_id', $villaId)->where('source', 'hostex')
                ->when($platformId, fn ($q) => $q->where('platform_id', $platformId))
                ->whereDate('check_in', $checkIn)->whereDate('check_out', $checkOut)
                ->whereHas('incomes', fn ($q) => $q->whereNotNull('hostex_stay_code'))->limit(2)->get();
            if ($automatic->isNotEmpty()) {
                return $automatic->count() === 1 ? $automatic->first() : null;
            }
        }

        $candidates = Booking::query()
            ->where('villa_id', $villaId)
            ->when($platformId, fn ($q) => $q->where('platform_id', $platformId))
            ->whereDate('check_in', $checkIn)
            ->when($checkOut, fn ($q) => $q->whereDate('check_out', $checkOut))
            // Jangan sampai menempel ke reservasi yang sudah punya kode booking lain.
            ->where(fn ($q) => $q->whereNull('booking_ref')->orWhere('booking_ref', ''))
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->limit(2)->get();

        return $candidates->count() === 1 ? $candidates->first() : null; // >1 kandidat → tidak ditebak
    }

    /** Apakah baris ini akan mengisi/mengubah harga reservasi? Harga manual tidak ditimpa; payout net bukan harga tamu. */
    private function priceChanges(Booking $booking, ?string $gross, bool $isNet): bool
    {
        return $gross !== null && ! $isNet && bccomp($gross, '0', 2) > 0
            && $booking->price_source !== 'manual'
            && ($booking->total_price === null || bccomp((string) $booking->total_price, $gross, 2) !== 0);
    }

    /** Lengkapi reservasi dari baris payout. @return bool true bila harga reservasi diisi/diperbarui */
    private function completeBooking(Booking $booking, array $r): bool
    {
        $changes = [];
        if ($r['guest'] && ! $booking->guest_name) {
            $changes['guest_name'] = Str::limit($r['guest'], 150, '');
        }
        if ($r['booking_ref'] && ! $booking->booking_ref) {
            $changes['booking_ref'] = $r['booking_ref'];
        }
        $setsPrice = $this->priceChanges($booking, $r['gross_amount'], (bool) $r['is_net_of_platform']);
        if ($setsPrice) {
            $changes['total_price'] = $r['gross_amount'];
            $changes['price_source'] = 'import';
        }
        if ($changes) {
            $booking->update($changes);
        }

        return $setsPrice;
    }

    /* ---------------- Parsing ---------------- */

    public function mapColumns(array $columns): array
    {
        $map = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $a) {
                if (in_array($a, $columns, true)) {
                    $map[$field] = $a;
                    break;
                }
            }
        }

        return $map;
    }

    /** "Rp 1.250.000", "1,250,000.00", "1.250.000,50", 1250000 → "1250000.00" */
    public function parseMoney(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return number_format((float) $v, 2, '.', '');
        }
        $s = preg_replace('/[^\d,.\-]/', '', (string) $v);
        if ($s === '' || $s === '-') {
            return null;
        }
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');

        if ($lastDot !== false && $lastComma !== false) {
            $dec = max($lastDot, $lastComma);
            $int = preg_replace('/[.,]/', '', substr($s, 0, $dec));
            $s = $int.'.'.substr($s, $dec + 1);
        } elseif ($lastComma !== false) {
            $s = preg_match('/,\d{1,2}$/', $s) && substr_count($s, ',') === 1 ? str_replace(',', '.', $s) : str_replace(',', '', $s);
        } elseif ($lastDot !== false) {
            $s = preg_match('/^\d{1,3}(\.\d{3})+$/', $s) ? str_replace('.', '', $s) : $s;
        }
        if (! is_numeric($s)) {
            return null;
        }

        return ($neg ? '-' : '').number_format((float) $s, 2, '.', '');
    }

    public function parseDate(mixed $v, string $format): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString();
            }
            $s = trim((string) $v);
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
                return Carbon::parse(substr($s, 0, 10))->toDateString();
            }
            if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})#', $s, $m)) {
                [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
                $y = $y < 100 ? 2000 + $y : $y;
                [$d, $mo] = $format === 'm/d/Y' ? [$b, $a] : [$a, $b];

                return checkdate($mo, $d, $y) ? Carbon::create($y, $mo, $d)->toDateString() : null;
            }

            return Carbon::parse($s)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseBool(mixed $v): bool
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'ya', 'y', 'yes', 'true', 'sudah'], true);
    }

    private function matchVilla(mixed $name, Collection $villas): ?Villa
    {
        $n = mb_strtolower(trim((string) $name));
        if ($n === '') {
            return null;
        }

        return $villas->first(fn ($v) => mb_strtolower($v->name) === $n)
            ?? $villas->first(fn ($v) => str_contains(mb_strtolower($v->name), $n) || str_contains($n, mb_strtolower($v->name)));
    }

    private function matchPlatform(mixed $name, Collection $platforms): ?BookingPlatform
    {
        $n = Str::slug((string) $name);
        if ($n === '') {
            return null;
        }

        return $platforms->first(fn ($p) => $p->slug === $n || Str::slug($p->name) === $n || str_contains($n, $p->slug));
    }
}
