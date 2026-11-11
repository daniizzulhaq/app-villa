<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Reservasi lain di villa yang sama yang tanggalnya beririsan.
     * Check-out dan check-in di hari yang sama (pergantian tamu) BUKAN bentrok.
     */
    public function conflicts(int $villaId, string|Carbon $checkIn, string|Carbon $checkOut, ?int $ignoreId = null): Collection
    {
        $in = Carbon::parse($checkIn)->toDateString();
        $out = Carbon::parse($checkOut)->toDateString();

        return Booking::query()
            ->with('platform:id,name')
            ->where('villa_id', $villaId)
            ->whereIn('status', BookingStatus::occupying())
            ->where('check_in', '<', $out)
            ->where('check_out', '>', $in)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderBy('check_in')
            ->get();
    }

    public function assertNoConflict(int $villaId, string|Carbon $checkIn, string|Carbon $checkOut, ?int $ignoreId = null): void
    {
        $clash = $this->conflicts($villaId, $checkIn, $checkOut, $ignoreId)->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'check_in' => sprintf(
                    __('Tanggal bentrok dengan reservasi %s (%s) %s – %s. Pilih tanggal lain.'),
                    $clash->guest_name ?: ($clash->booking_ref ?: '#'.$clash->id),
                    $clash->platform?->name,
                    $clash->check_in->translatedFormat('d M'),
                    $clash->check_out->translatedFormat('d M Y'),
                ),
            ]);
        }
    }

    public function create(array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($data, $actor) {
            if (in_array($data['status'], BookingStatus::occupying(), true)) {
                $this->assertNoConflict((int) $data['villa_id'], $data['check_in'], $data['check_out']);
            }

            return Booking::create(Arr::only($data, (new Booking)->getFillable()) + [
                'source' => 'manual',
                'price_source' => isset($data['total_price']) ? 'manual' : null,
                'created_by' => $actor->id,
                'cancelled_at' => $data['status'] === BookingStatus::Cancelled->value ? now() : null,
            ]);
        });
    }

    public function update(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data) {
            if ($booking->source === 'hostex') {
                $data = Arr::only($data, ['notes', 'total_price', 'guest_phone']);
            }
            // Tanggal, villa & platform booking dari iCal milik platform → tidak diubah manual.
            if ($booking->isFromPlatformSync()) {
                $data = Arr::except($data, ['villa_id', 'platform_id', 'check_in', 'check_out']);
            }

            $booking->fill(Arr::only($data, ['villa_id', 'platform_id', 'booking_ref', 'guest_name', 'guest_count', 'guest_phone', 'check_in', 'check_out', 'total_price', 'status', 'notes']));
            if ($booking->isDirty('total_price')) {
                // Harga diubah manual → sinkron iCal tidak akan menimpanya lagi.
                $booking->price_source = $booking->total_price === null ? null : 'manual';
            }

            if (in_array($booking->status->value, BookingStatus::occupying(), true)
                && ($booking->isDirty(['check_in', 'check_out', 'villa_id', 'status']))) {
                $this->assertNoConflict($booking->villa_id, $booking->check_in, $booking->check_out, $booking->id);
            }

            if ($booking->isDirty('status')) {
                $booking->cancelled_at = $booking->status === BookingStatus::Cancelled ? now() : null;
            }

            $booking->save();

            return $booking;
        });
    }

    /** Normalize legacy stay states without changing payment or hold statuses. */
    public function advanceStatuses(): int
    {
        $legacy = Booking::whereIn('status', [BookingStatus::CheckedIn->value, BookingStatus::CheckedOut->value])->get();
        $legacy->each(fn (Booking $booking) => $booking->update(['status' => BookingStatus::Confirmed]));

        return $legacy->count();
    }
}
