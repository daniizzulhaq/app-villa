<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\HostexException;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Villa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HostexSyncService
{
    public function __construct(private HostexClient $client) {}

    /** Pulls bookings into the local database; never writes to Hostex or the OTA. */
    public function syncVilla(Villa $villa, ?string $from = null, ?string $to = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'cancelled' => 0, 'skipped' => 0, 'foreign_currency' => 0, 'error' => null];
        try {
            return Cache::store('file')->lock('hostex-sync', 300)->block(5, function () use ($villa, $from, $to, $result) {
                if (config('propertyme.channel_manager_provider') !== 'hostex' || ! $villa->hostex_property_id || ! $villa->is_active) {
                    throw new HostexException('An active villa with a verified Hostex mapping is required.');
                }
                if ($villa->channels()->where('is_active', true)->whereNotNull('ical_url')->where('ical_url', '!=', '')->exists()) {
                    throw new HostexException('Disable this villa\'s iCal imports before using Hostex to avoid duplicate bookings.');
                }
                $from ??= today($villa->timezone)->subYear()->toDateString();
                $to ??= today($villa->timezone)->addYear()->toDateString();
                $rows = $this->client->reservations((int) $villa->hostex_property_id, $from, $to);
                $platforms = BookingPlatform::pluck('id', 'slug');

                return DB::transaction(function () use ($villa, $rows, $platforms, $result) {
                    $current = Villa::lockForUpdate()->findOrFail($villa->id);
                    if ((string) $current->hostex_property_id !== (string) $villa->hostex_property_id || ! $current->is_active) {
                        throw new HostexException('Villa mapping changed during sync. Retry the synchronization.');
                    }
                    foreach ($rows as $row) {
                        $attributes = $this->attributes($current, $row, $platforms->all());
                        $booking = Booking::withTrashed()->where('hostex_stay_code', $row['stay_code'])->first();
                        if ($booking && ($booking->source !== 'hostex' || $booking->villa_id !== $current->id)) {
                            throw new HostexException('A Hostex stay is already assigned to another local villa. Reconcile its allocation first.');
                        }
                        if ($booking?->trashed()) {
                            $result['skipped']++;

                            continue;
                        }
                        if ($attributes['booking_ref'] && Booking::where('villa_id', $current->id)
                            ->where('platform_id', $attributes['platform_id'])->where('booking_ref', $attributes['booking_ref'])
                            ->where('source', '!=', 'hostex')->exists()) {
                            throw new HostexException('A booking reference already exists outside Hostex. Reconcile it before importing to avoid duplicates.');
                        }
                        $new = ! $booking;
                        $booking ??= new Booking;
                        $newCancellation = $attributes['status'] === BookingStatus::Cancelled && $booking->status !== BookingStatus::Cancelled;
                        // A manually entered or reconciled payout price stays under local control.
                        if (! $new && in_array($booking->price_source, ['manual', 'import'], true)) {
                            unset($attributes['total_price'], $attributes['price_source']);
                        }
                        // Repeated cancellations retain their original local timestamp.
                        if ($attributes['status'] === BookingStatus::Cancelled && $booking->cancelled_at) {
                            $attributes['cancelled_at'] = $booking->cancelled_at;
                        }
                        $booking->forceFill($attributes);
                        if ($attributes['hostex_rate_currency'] && $attributes['hostex_rate_currency'] !== 'IDR') {
                            $result['foreign_currency']++;
                        }
                        if ($new || $booking->isDirty()) {
                            $booking->save();
                            $result[$new ? 'created' : 'updated']++;
                            $result['cancelled'] += (int) $newCancellation;
                        } else {
                            $result['unchanged']++;
                        }
                    }
                    // Absence from a filtered response never cancels an existing booking.
                    $current->forceFill(['hostex_last_synced_at' => now(), 'hostex_last_success_at' => now(), 'hostex_last_error' => null])->save();

                    return $result;
                });
            });
        } catch (Throwable $exception) {
            $result['error'] = $exception instanceof HostexException ? $exception->getMessage() : 'Hostex sync failed. No booking changes saved; check configuration and try again.';
            // Diagnostic categories only: never log SQL bindings, guest data or HTTP headers.
            $result['diagnostic'] = class_basename($exception);
            if ($exception instanceof QueryException) {
                $code = (string) ($exception->errorInfo[0] ?? '');
                $result['diagnostic'] .= preg_match('/^[A-Z0-9]{5}$/', $code) ? ' SQLSTATE '.$code : '';
            }
            $villa->forceFill(['hostex_last_synced_at' => now(), 'hostex_last_error' => $result['error']])->save();

            return $result;
        }
    }

    private function attributes(Villa $villa, array $row, array $platforms): array
    {
        if ((string) ($row['property_id'] ?? '') !== (string) $villa->hostex_property_id
            || ! is_string($row['stay_code'] ?? null) || $row['stay_code'] === '' || mb_strlen($row['stay_code']) > 150
            || ! $this->validDate($row['check_in_date'] ?? null) || ! $this->validDate($row['check_out_date'] ?? null)
            || $row['check_out_date'] <= $row['check_in_date']) {
            throw new HostexException('Hostex returned an invalid stay identity, allocation or date. No booking changes saved.');
        }
        $today = today($villa->timezone)->toDateString();
        $status = match ($row['status'] ?? null) {
            'accepted' => $row['check_out_date'] <= $today ? BookingStatus::CheckedOut : ($row['check_in_date'] <= $today ? BookingStatus::CheckedIn : BookingStatus::Confirmed),
            'cancelled', 'denied', 'timeout' => BookingStatus::Cancelled,
            'wait_accept', 'wait_pay' => BookingStatus::NeedsCompletion,
            default => throw new HostexException('Hostex returned an unsupported reservation status. No booking changes saved.'),
        };
        $slug = match ($row['channel_type'] ?? '') {
            'airbnb' => BookingPlatform::AIRBNB,
            'booking', 'bookingcom', 'booking.com' => BookingPlatform::BOOKING_COM,
            'direct', 'hostex', 'hostex_direct', 'booking_site' => BookingPlatform::DIRECT,
            default => BookingPlatform::OTHER,
        };
        if (! isset($platforms[$slug])) {
            throw new HostexException('The booking platform dictionary is missing. Seed the booking platforms first.');
        }
        $rate = $this->money($row['rates']['rate'] ?? null);
        $commission = $this->money($row['rates']['commission'] ?? null);
        $count = $row['number_of_guests'] ?? null;
        if ($count !== null && (! is_int($count) || $count < 0 || $count > 65535)) {
            throw new HostexException('Hostex returned an invalid guest count.');
        }
        $arrival = $row['check_in_details']['arrival_at'] ?? null;
        $arrivalTime = is_array($arrival) && is_int($arrival['hour'] ?? null) && is_int($arrival['minute'] ?? null)
            && $arrival['hour'] >= 0 && $arrival['hour'] < 24 && $arrival['minute'] >= 0 && $arrival['minute'] < 60
            ? sprintf('%02d:%02d:00', $arrival['hour'], $arrival['minute']) : null;
        $phone = preg_replace('/\D/', '', (string) ($row['guest_phone'] ?? ''));

        return [
            'villa_id' => $villa->id, 'platform_id' => $platforms[$slug], 'source' => 'hostex',
            'hostex_stay_code' => $row['stay_code'], 'hostex_status' => $row['status'],
            'booking_ref' => mb_substr((string) (($row['channel_id'] ?? '') ?: ($row['reservation_code'] ?? '')), 0, 100) ?: null,
            'guest_name' => mb_substr(trim((string) ($row['guest_name'] ?? '')), 0, 150) ?: null,
            'guest_count' => $count ?: null, 'phone_last4' => strlen($phone) >= 4 ? substr($phone, -4) : null,
            'check_in' => $row['check_in_date'], 'check_out' => $row['check_out_date'], 'arrival_time' => $arrivalTime,
            'status' => $status, 'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
            'hostex_rate_amount' => $rate['amount'], 'hostex_rate_currency' => $rate['currency'],
            'hostex_commission_amount' => $commission['amount'], 'hostex_commission_currency' => $commission['currency'],
            // Use per-stay rate, never total_rate/payment which may repeat on multi-stay orders.
            'total_price' => $rate['currency'] === 'IDR' ? $rate['amount'] : null,
            'price_source' => $rate['currency'] === 'IDR' && $rate['amount'] !== null ? 'hostex' : null,
        ];
    }

    private function money(mixed $money): array
    {
        if (! is_array($money) || ! isset($money['amount'], $money['currency'])) {
            return ['amount' => null, 'currency' => null];
        }
        $currency = strtoupper((string) $money['currency']);
        if (! preg_match('/^[A-Z]{3}$/', $currency) || ! is_numeric($money['amount']) || ! is_finite((float) $money['amount'])
            || $money['amount'] < 0 || $money['amount'] > 9999999999999.99) {
            throw new HostexException('Hostex returned an invalid financial amount or currency.');
        }

        return ['amount' => number_format((float) $money['amount'], 2, '.', ''), 'currency' => $currency];
    }

    private function validDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/', $date)) {
            return false;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
