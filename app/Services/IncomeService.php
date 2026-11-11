<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Income;
use App\Models\User;
use App\Models\Villa;
use App\Services\Finance\FinanceCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncomeService
{
    public function __construct(private ImageCompressor $images) {}

    /**
     * Snapshot fee: % & basis diambil dari riwayat fee yang berlaku pada payment_date,
     * sehingga perubahan % fee di kemudian hari tidak mengubah pemasukan ini.
     */
    public function compute(array $data): array
    {
        $villa = Villa::findOrFail($data['villa_id']);
        $fee = $villa->feeOn($data['payment_date']);
        $pct = (string) ($fee?->fee_percentage ?? $villa->fee_percentage);
        $basis = $fee?->fee_basis ?? $villa->fee_basis;

        $line = FinanceCalculator::incomeLine(
            $data['gross_amount'], $data['platform_fee'] ?? 0, $data['other_deduction'] ?? 0,
            (bool) ($data['is_net_of_platform'] ?? false), $pct, $basis, $data['fee_base_amount'] ?? null,
        );

        return [
            'net_revenue' => $line['net_revenue'],
            'fee_percentage' => $pct,
            'fee_basis' => $basis,
            'fee_base_amount' => $line['fee_base_amount'],
            'fee_amount' => $line['fee_amount'],
        ];
    }

    public function create(array $data, User $actor, array $files = []): Income
    {
        return DB::transaction(function () use ($data, $actor, $files) {
            $data = $this->withBookingDefaults($data);
            $this->preventAutomaticDuplicate($data);
            $income = Income::create($this->fields($data) + $this->compute($data) + ['created_by' => $actor->id]);
            $this->storeAttachments($income, $files);
            $this->confirmBooking($income);

            return $income;
        });
    }

    public function update(Income $income, array $data, array $files = [], array $removeIds = []): Income
    {
        $this->guardAutomatic($income);

        return DB::transaction(function () use ($income, $data, $files, $removeIds) {
            $data = $this->withBookingDefaults($data + ['_ignore_id' => $income->id]);
            $this->preventAutomaticDuplicate($data);
            $income->update($this->fields($data) + $this->compute($data));

            foreach ($income->attachments()->whereIn('id', $removeIds)->get() as $att) {
                Storage::disk('local')->delete($att->path);
                $att->delete();
            }
            $this->storeAttachments($income, $files);

            return $income;
        });
    }

    public function delete(Income $income): void
    {
        $this->guardAutomatic($income);
        DB::transaction(function () use ($income) {
            // Lepas kode booking agar bisa dicatat/import ulang tanpa melanggar unique key.
            $income->forceFill(['booking_ref' => null])->saveQuietly();
            $income->delete();
        });
    }

    private function fields(array $d): array
    {
        return [
            'booking_id' => $d['booking_id'] ?? null,
            'villa_id' => $d['villa_id'],
            'platform_id' => $d['platform_id'],
            'booking_ref' => filled($d['booking_ref'] ?? null) ? trim($d['booking_ref']) : null,
            'payment_date' => $d['payment_date'],
            'gross_amount' => $d['gross_amount'],
            'platform_fee' => $d['platform_fee'] ?? 0,
            'other_deduction' => $d['other_deduction'] ?? 0,
            'is_net_of_platform' => (bool) ($d['is_net_of_platform'] ?? false),
            'notes' => $d['notes'] ?? null,
            'import_batch_id' => $d['import_batch_id'] ?? null,
        ];
    }

    /** Pemasukan dari booking mewarisi villa, platform & kode booking-nya. */
    private function withBookingDefaults(array $d): array
    {
        if (! empty($d['booking_id']) && $b = Booking::lockForUpdate()->find($d['booking_id'])) {
            $d['villa_id'] = $b->villa_id;
            $d['platform_id'] ??= $b->platform_id;
            // Warisi kode booking hanya jika belum dipakai pemasukan lain (mis. pembayaran ke-2 / cicilan).
            if (! filled($d['booking_ref'] ?? null) && $b->booking_ref
                && ! Income::where('villa_id', $b->villa_id)->where('platform_id', $d['platform_id'])->where('booking_ref', $b->booking_ref)
                    ->when($d['_ignore_id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))->exists()) {
                $d['booking_ref'] = $b->booking_ref;
            }
        }

        return $d;
    }

    private function guardAutomatic(Income $income): void
    {
        if ($income->isFromHostex()) {
            throw ValidationException::withMessages(['income' => __('Automatic Hostex income is managed by synchronization.')]);
        }
    }

    private function preventAutomaticDuplicate(array $data): void
    {
        $existing = Income::whereNotNull('hostex_stay_code')->where(function ($q) use ($data) {
            $q->whereRaw('1 = 0');
            if (! empty($data['booking_id'])) {
                $q->orWhere('booking_id', $data['booking_id']);
            }
            if (filled($data['booking_ref'] ?? null)) {
                $q->orWhere(fn ($ref) => $ref->where('villa_id', $data['villa_id'])->where('platform_id', $data['platform_id'])
                    ->where('booking_ref', $data['booking_ref']));
            }
        })->lockForUpdate()->first();
        if ($existing) {
            throw ValidationException::withMessages(['booking_id' => __('This booking already has automatic Hostex income. Open the existing entry.')]);
        }
    }

    private function confirmBooking(Income $income): void
    {
        $b = $income->booking;
        if ($b && $b->status === BookingStatus::NeedsCompletion && $b->guest_name) {
            $b->update(['status' => BookingStatus::Confirmed]);
        }
    }

    /** Bukti bayar (screenshot) disimpan privat. */
    public function storeAttachments(Income $income, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $meta = str_starts_with((string) $file->getMimeType(), 'image/')
                ? $this->images->store($file, 'income-proofs/'.$income->villa_id, 'local')
                : $this->storeRaw($file, 'income-proofs/'.$income->villa_id);
            $income->attachments()->create($meta);
        }
    }

    private function storeRaw(UploadedFile $file, string $dir): array
    {
        $path = $file->storeAs($dir.'/'.now()->format('Y/m'), Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');

        return ['path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 250, ''), 'size' => $file->getSize()];
    }
}
