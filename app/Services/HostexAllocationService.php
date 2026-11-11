<?php

namespace App\Services;

use App\Exceptions\HostexException;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Income;
use App\Models\Villa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HostexAllocationService
{
    public function __construct(private HostexClient $client) {}

    /** Preview by default. Move existing Hostex bookings only; never move incomes. */
    public function reconcile(Villa $target, ?int $sourceId = null, bool $apply = false, ?string $from = null, ?string $to = null): array
    {
        $result = ['entries' => [], 'eligible' => 0, 'conflicts' => 0, 'moved' => 0, 'error' => null];
        try {
            return Cache::store('file')->lock('hostex-sync', 300)->block(5, function () use ($target, $sourceId, $apply, $from, $to, $result) {
                if (config('propertyme.channel_manager_provider') !== 'hostex' || ! $target->hostex_property_id || ! $target->is_active) {
                    throw new HostexException('An active villa with a verified Hostex mapping is required.');
                }
                if ($apply && (! $sourceId || $sourceId === $target->id)) {
                    throw new HostexException('Applying allocation changes requires a different, explicit --from-villa=ID.');
                }
                if ($sourceId && ! Villa::whereKey($sourceId)->whereNotNull('hostex_property_id')->exists()) {
                    throw new HostexException('The source villa must exist and have a Hostex mapping.');
                }
                if (count($this->client->properties((int) $target->hostex_property_id)) !== 1) {
                    throw new HostexException('The target Hostex property was not found.');
                }
                $from ??= today($target->timezone)->subYear()->toDateString();
                $to ??= today($target->timezone)->addYear()->toDateString();
                $rows = $this->client->reservations((int) $target->hostex_property_id, $from, $to);
                foreach ($rows as $row) {
                    if (! is_string($row['stay_code']) || $row['stay_code'] === '' || mb_strlen($row['stay_code']) > 150) {
                        throw new HostexException('Hostex returned an invalid stay identity. No booking allocations changed.');
                    }
                }
                $codes = array_column($rows, 'stay_code');
                $candidates = Booking::withTrashed()->whereIn('hostex_stay_code', $codes)->get();
                $sourceVillas = Villa::withTrashed()->whereIn('id', $candidates->pluck('villa_id')->push($target->id)->unique())
                    ->get()->keyBy('id');
                $sourceStays = [];
                foreach ($sourceVillas as $source) {
                    if ($source->id === $target->id || $source->trashed() || ! $source->hostex_property_id
                        || (string) $source->hostex_property_id === (string) $target->hostex_property_id) {
                        continue;
                    }
                    if (count($this->client->properties((int) $source->hostex_property_id)) === 1) {
                        $sourceStays[$source->id] = array_fill_keys(array_column(
                            $this->client->reservations((int) $source->hostex_property_id, $from, $to), 'stay_code'), true);
                    }
                }
                $platforms = BookingPlatform::pluck('id', 'slug')->all();

                return DB::transaction(function () use ($target, $sourceId, $apply, $rows, $codes, $sourceVillas, $sourceStays, $platforms, $result) {
                    $villas = Villa::withTrashed()->whereIn('id', $sourceVillas->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    $current = $villas->get($target->id);
                    if (! $current || $current->trashed() || ! $current->is_active
                        || (string) $current->hostex_property_id !== (string) $target->hostex_property_id) {
                        throw new HostexException('Villa mapping changed during allocation review. Retry the preview.');
                    }
                    if ($apply && $current->channels()->where('is_active', true)->whereNotNull('ical_url')->where('ical_url', '!=', '')->exists()) {
                        throw new HostexException('Disable the target villa\'s iCal imports before applying allocation changes.');
                    }
                    foreach ($sourceVillas as $source) {
                        if ((string) $villas->get($source->id)?->hostex_property_id !== (string) $source->hostex_property_id) {
                            throw new HostexException('Source mapping changed during allocation review. Retry the preview.');
                        }
                    }
                    $bookings = Booking::withTrashed()->whereIn('hostex_stay_code', $codes)->lockForUpdate()->get()->keyBy('hostex_stay_code');
                    $moves = [];
                    foreach ($rows as $row) {
                        $booking = $bookings->get($row['stay_code']);
                        if (! $booking || ($booking->source === 'hostex' && $booking->villa_id === $current->id && ! $booking->trashed())) {
                            continue;
                        }
                        $source = $villas->get($booking->villa_id);
                        $slug = match ($row['channel_type'] ?? '') {
                            'airbnb' => BookingPlatform::AIRBNB,
                            'booking', 'bookingcom', 'booking.com' => BookingPlatform::BOOKING_COM,
                            'direct', 'hostex', 'hostex_direct', 'booking_site' => BookingPlatform::DIRECT,
                            default => BookingPlatform::OTHER,
                        };
                        $ref = mb_substr((string) (($row['channel_id'] ?? '') ?: ($row['reservation_code'] ?? '')), 0, 100) ?: null;
                        $incomes = Income::withTrashed()->where(function ($query) use ($booking, $current) {
                            $query->where('booking_id', $booking->id);
                            if ($booking->booking_ref) {
                                $query->orWhere(fn ($q) => $q->whereIn('villa_id', [$booking->villa_id, $current->id])
                                    ->where('platform_id', $booking->platform_id)->where('booking_ref', $booking->booking_ref));
                            }
                        })->count();
                        $duplicate = Booking::withTrashed()->where('villa_id', $current->id)->whereKeyNot($booking->id)
                            ->where(function ($q) use ($booking) {
                                $q->whereRaw('1 = 0');
                                if ($booking->ical_uid) {
                                    $q->orWhere('ical_uid', $booking->ical_uid);
                                }
                                if ($booking->booking_ref) {
                                    $q->orWhere(fn ($refs) => $refs->where('platform_id', $booking->platform_id)->where('booking_ref', $booking->booking_ref));
                                }
                                $q->orWhere(fn ($legacy) => $legacy->where('source', '!=', 'hostex')
                                    ->where('platform_id', $booking->platform_id)
                                    ->whereDate('check_in', $booking->check_in->toDateString())
                                    ->whereDate('check_out', $booking->check_out->toDateString()));
                            })->exists();
                        $reason = match (true) {
                            $booking->trashed() => 'Booking is deleted; manual review required.',
                            $booking->source !== 'hostex' => 'The existing stay is not a Hostex booking.',
                            $booking->villa_id === $current->id => 'The stay already belongs to this villa; review its identity.',
                            $sourceId !== null && $booking->villa_id !== $sourceId => 'Current villa differs from the specified --from-villa.',
                            ! $source || $source->trashed() || ! $source->hostex_property_id => 'Source villa is deleted or has no verified Hostex mapping.',
                            ! array_key_exists($source->id, $sourceStays) => 'The source Hostex property could not be verified.',
                            isset($sourceStays[$source->id][$row['stay_code']]) => 'Stay still appears in the source Hostex property.',
                            $incomes > 0 => 'Linked or matching income exists; review financial allocation first.',
                            $duplicate => 'The target villa already has a matching reference, legacy dates or iCal identity.',
                            ! isset($platforms[$slug]) || $booking->platform_id !== $platforms[$slug]
                                || $booking->booking_ref !== $ref
                                || $booking->check_in->toDateString() !== ($row['check_in_date'] ?? null)
                                || $booking->check_out->toDateString() !== ($row['check_out_date'] ?? null) => 'Platform, reference or dates differ from Hostex; review first.',
                            default => null,
                        };
                        $result['entries'][] = [
                            'booking_id' => $booking->id, 'from_villa' => $booking->villa_id, 'to_villa' => $current->id,
                            'check_in' => $booking->check_in->toDateString(), 'check_out' => $booking->check_out->toDateString(),
                            'incomes' => $incomes, 'reason' => $reason,
                        ];
                        $result[$reason === null ? 'eligible' : 'conflicts']++;
                        if ($reason === null) {
                            $moves[] = $booking;
                        }
                    }
                    if ($apply && $result['conflicts']) {
                        $result['error'] = 'Allocation conflicts found. No booking allocations changed. Review the listed booking IDs.';

                        return $result;
                    }
                    if ($apply) {
                        foreach ($moves as $booking) {
                            $booking->forceFill(['villa_id' => $current->id])->save();
                            $result['moved']++;
                        }
                    }

                    return $result;
                });
            });
        } catch (Throwable $exception) {
            $result['error'] = $exception instanceof HostexException ? $exception->getMessage()
                : 'Hostex allocation check failed. No booking allocations changed. Check configuration and retry.';

            return $result;
        }
    }
}
