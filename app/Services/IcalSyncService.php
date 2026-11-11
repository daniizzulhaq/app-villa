<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Villa;
use App\Models\VillaChannel;
use App\Notifications\BookingSyncedNotification;
use App\Support\Notify;
use ICal\ICal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sinkron iCal Airbnb / Booking.com.
 * - Booking baru → status "Perlu dilengkapi" (iCal hanya berisi tanggal).
 * - Harga: Airbnb & Booking.com TIDAK menyertakan harga di iCal. Bila feed lain (channel manager,
 *   iCal kustom) menuliskan harga di SUMMARY/DESCRIPTION, harga ikut tersimpan otomatis.
 * - Booking yang hilang dari feed (dan belum lewat) → "Cancelled".
 * - Feed kosong padahal ada booking mendatang → pembatalan DITAHAN (cegah salah batal massal).
 */
class IcalSyncService
{
    /** @param  array<int>|null  $villaIds  batasi ke villa tertentu (Manager wilayah); null = semua */
    public function syncAll(?array $villaIds = null): array
    {
        $results = [];
        VillaChannel::query()->with(['villa', 'platform'])
            ->when($villaIds !== null, fn ($q) => $q->whereIn('villa_id', $villaIds ?: [0]))
            ->where('is_active', true)->whereNotNull('ical_url')
            ->whereHas('villa', fn ($q) => $q->where('is_active', true)
                ->when(config('propertyme.channel_manager_provider') === 'hostex', fn ($q) => $q->whereNull('hostex_property_id')))
            ->each(function (VillaChannel $c) use (&$results) {
                $results[] = $this->syncChannel($c);
            });

        return $results;
    }

    public function syncVilla(int $villaId): array
    {
        return VillaChannel::query()->with(['villa', 'platform'])
            ->where('villa_id', $villaId)->where('is_active', true)->whereNotNull('ical_url')
            ->get()->map(fn (VillaChannel $c) => $this->syncChannel($c))->all();
    }

    /** @return array{channel:VillaChannel, created:int, updated:int, cancelled:int, error:?string, warning:?string} */
    public function syncChannel(VillaChannel $channel): array
    {
        $result = ['channel' => $channel, 'created' => 0, 'updated' => 0, 'cancelled' => 0, 'error' => null, 'warning' => null];
        try {
            $channel->refresh()->load(['villa', 'platform']);
            if (! $channel->is_active || blank($channel->ical_url) || ! $channel->villa?->is_active) {
                throw new \RuntimeException('ICAL_DISABLED');
            }
            if (config('propertyme.channel_manager_provider') === 'hostex' && $channel->villa?->hostex_property_id) {
                throw new \RuntimeException('This villa uses Hostex. Use Hostex synchronization instead of iCal.');
            }
            $events = $this->fetchEvents($channel);
            [$created, $updated, $cancelled, $warning] = $this->apply($channel, $events);

            $result = array_merge($result, [
                'created' => count($created), 'updated' => $updated, 'cancelled' => count($cancelled), 'warning' => $warning,
            ]);
            $channel->forceFill(['last_synced_at' => now(), 'last_success_at' => now(), 'last_error' => $warning])->save();

            if ($created || $cancelled) {
                Notify::managers(new BookingSyncedNotification($channel->villa, $channel->platform, $created, $cancelled), $channel->villa_id);
            }
        } catch (Throwable $e) {
            if ($e->getMessage() === 'ICAL_DISABLED') {
                $result['warning'] = __('iCal synchronization is disabled for this channel.');

                return $result;
            }
            $result['error'] = $this->friendlyError($e);
            $channel->forceFill(['last_synced_at' => now(), 'last_error' => $result['error']])->save();
            Log::warning('iCal sync gagal', ['channel' => $channel->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    /** @return array<int, array{uid:string, check_in:string, check_out:string, ref:?string, summary:string, price:?string, phone_last4:?string}> */
    public function fetchEvents(VillaChannel $channel): array
    {
        // Ulangi hanya untuk gangguan koneksi; 4xx (link salah) tidak perlu dicoba ulang.
        $response = Http::timeout(25)
            ->retry(2, 1500, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->withUserAgent('PropertyMe/1.0 (+calendar sync)')
            ->get($channel->ical_url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        return $this->parse($response->body(), $channel->platform?->slug, $channel->villa?->timezone);
    }

    public function parse(string $body, ?string $platformSlug, ?string $timezone = null): array
    {
        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            throw new \UnexpectedValueException('NOT_ICAL');
        }

        $ical = new ICal(false, ['skipRecurrence' => true, 'defaultTimeZone' => $timezone ?: config('app.timezone')]);
        $ical->initString($body);

        $out = [];
        foreach ($ical->events() as $e) {
            $summary = trim((string) $e->summary);
            // Airbnb: tanggal yang diblok pemilik ("Airbnb (Not available)") bukan reservasi.
            if ($platformSlug === BookingPlatform::AIRBNB && preg_match('/not available/i', $summary)) {
                continue;
            }
            $in = $this->dateOf($e->dtstart);
            $outDate = $e->dtend ? $this->dateOf($e->dtend) : Carbon::parse($in)->addDay()->toDateString();
            if (! $in || ! $outDate || $outDate <= $in || ! $e->uid) {
                continue;
            }
            preg_match('~details/([A-Z0-9]{6,})~', (string) $e->description, $m);
            // Airbnb: "Phone Number (Last 4 Digits): 1234"
            preg_match('~last\s*4\s*digits\)?\s*:?\s*(\d{4})\b~i', (string) $e->description, $phone);

            $out[] = [
                'uid' => mb_substr((string) $e->uid, 0, 250),
                'check_in' => $in,
                'check_out' => $outDate,
                'ref' => $m[1] ?? null,
                'phone_last4' => $phone[1] ?? null,
                'summary' => $summary,
                'price' => $this->priceOf($summary."\n".(string) $e->description),
            ];
        }

        return $out;
    }

    private function apply(VillaChannel $channel, array $events): array
    {
        return DB::transaction(function () use ($channel, $events) {
            // A feed can be disabled while its HTTP request is in flight.
            $villa = Villa::lockForUpdate()->findOrFail($channel->villa_id);
            $current = VillaChannel::lockForUpdate()->find($channel->id);
            if (! $current || ! $current->is_active || blank($current->ical_url)
                || $current->ical_url !== $channel->ical_url || ! $villa->is_active
                || (config('propertyme.channel_manager_provider') === 'hostex' && $villa->hostex_property_id)) {
                throw new \RuntimeException('ICAL_DISABLED');
            }
            $created = [];
            $updated = 0;
            $seen = [];

            foreach ($events as $ev) {
                $seen[] = $ev['uid'];
                $booking = Booking::withTrashed()->firstOrNew(['villa_id' => $channel->villa_id, 'ical_uid' => $ev['uid']]);

                if (! $booking->exists) {
                    $booking->fill([
                        'platform_id' => $channel->platform_id,
                        'source' => 'ical',
                        'booking_ref' => $ev['ref'],
                        'phone_last4' => $ev['phone_last4'] ?? null,
                        'check_in' => $ev['check_in'],
                        'check_out' => $ev['check_out'],
                        'total_price' => $ev['price'] ?? null,
                        'price_source' => isset($ev['price']) ? 'ical' : null,
                        'status' => BookingStatus::NeedsCompletion,
                    ])->save();
                    $created[] = $booking->id;

                    continue;
                }

                if ($booking->trashed()) {
                    continue; // dihapus Manager secara sengaja
                }

                $changes = ['check_in' => $ev['check_in'], 'check_out' => $ev['check_out']];
                if (! $booking->booking_ref && $ev['ref']) {
                    $changes['booking_ref'] = $ev['ref'];
                }
                if (! empty($ev['phone_last4'])) {
                    $changes['phone_last4'] = $ev['phone_last4']; // ikut berubah bila tamu ganti nomor
                }
                // Harga dari feed hanya menimpa harga hasil sinkron, bukan yang diisi manual / dari import payout.
                if (isset($ev['price']) && in_array($booking->price_source, [null, 'ical'], true)) {
                    $changes['total_price'] = $ev['price'];
                    $changes['price_source'] = 'ical';
                }
                if ($booking->status === BookingStatus::Cancelled) {
                    // Muncul lagi di feed → aktif kembali
                    $changes['status'] = $booking->guest_name ? BookingStatus::Confirmed : BookingStatus::NeedsCompletion;
                    $changes['cancelled_at'] = null;
                }
                $booking->fill($changes);
                if ($booking->isDirty()) {
                    $booking->save();
                    $updated++;
                }
            }

            // Booking mendatang yang hilang dari feed → dibatalkan
            $today = now($channel->villa?->timezone ?: config('app.timezone'))->toDateString();
            $missing = Booking::query()
                ->where('villa_id', $channel->villa_id)
                ->where('platform_id', $channel->platform_id)
                ->where('source', 'ical')
                ->where('status', '!=', BookingStatus::Cancelled->value)
                ->where('check_out', '>=', $today)
                ->when($seen, fn ($q) => $q->whereNotIn('ical_uid', $seen))
                ->get();

            $warning = null;
            $cancelled = [];
            if ($events === [] && $missing->count() >= 2) {
                $warning = __('Feed iCal kosong padahal ada :count reservasi mendatang. Pembatalan otomatis ditahan — periksa link iCal.', ['count' => $missing->count()]);
            } else {
                foreach ($missing as $b) {
                    $b->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
                    $cancelled[] = $b->id;
                }
            }

            return [$created, $updated, $cancelled, $warning];
        });
    }

    /**
     * Cari harga di teks event, mis. "Total: Rp 2.500.000", "Price: IDR 1,250,000.00", "Harga 3500000".
     * Hanya Rupiah (atau tanpa mata uang) yang diambil — mata uang lain diabaikan agar laporan tidak salah.
     */
    public function priceOf(string $text): ?string
    {
        $label = '(?:total(?:\s*(?:price|amount|harga))?|price|harga|amount|payout|revenue|tarif|rate)';
        if (! preg_match('/\b'.$label.'\b\s*[:=]?\s*(IDR|Rp\.?|[A-Z]{3}|[$€£])?\s*([\d][\d.,]*)/iu', $text, $m)) {
            return null;
        }
        $currency = strtoupper(rtrim($m[1] ?? '', '.'));
        if ($currency !== '' && ! in_array($currency, ['IDR', 'RP'], true)) {
            return null;
        }

        $amount = $this->normalizeAmount($m[2]);

        return $amount !== null && (float) $amount > 0 ? $amount : null;
    }

    private function normalizeAmount(string $raw): ?string
    {
        $raw = rtrim($raw, '.,');
        $lastDot = strrpos($raw, '.');
        $lastComma = strrpos($raw, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Pemisah yang terakhir adalah desimal: "1.250.000,50" atau "1,250,000.50"
            $dec = $lastDot > $lastComma ? '.' : ',';
            $thousand = $dec === '.' ? ',' : '.';
            $raw = str_replace([$thousand, $dec], ['', '.'], $raw);
        } elseif ($lastDot !== false || $lastComma !== false) {
            $sep = $lastDot !== false ? '.' : ',';
            $parts = explode($sep, $raw);
            $isThousands = count($parts) > 2 || strlen(end($parts)) === 3;
            $raw = $isThousands ? implode('', $parts) : implode('.', $parts);
        }

        return is_numeric($raw) ? number_format((float) $raw, 2, '.', '') : null;
    }

    private function dateOf(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        return strlen($digits) >= 8 ? substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2) : null;
    }

    private function friendlyError(Throwable $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            $msg === 'NOT_ICAL' => __('Link tidak berisi kalender iCal. Salin ulang link ekspor dari platform.'),
            str_starts_with($msg, 'HTTP 404'), str_starts_with($msg, 'HTTP 410') => __('Link iCal tidak ditemukan (404). Kemungkinan link sudah diganti di platform.'),
            str_starts_with($msg, 'HTTP 403'), str_starts_with($msg, 'HTTP 401') => __('Akses ke link iCal ditolak. Buat ulang link ekspor di platform.'),
            str_starts_with($msg, 'HTTP') => __('Platform membalas dengan error (:error). Coba lagi nanti.', ['error' => $msg]),
            str_contains(strtolower($msg), 'timed out') || str_contains(strtolower($msg), 'curl') => __('Tidak bisa terhubung ke platform (timeout/jaringan). Akan dicoba lagi otomatis.'),
            default => __('Gagal membaca iCal: :error', ['error' => mb_substr($msg, 0, 180)]),
        };
    }
}
