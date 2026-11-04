<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\Villa;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/**
 * Feed iCal publik per villa (dilindungi token acak) untuk diimpor ke Airbnb / Booking.com,
 * agar tanggal Direct Booking & platform lain ikut terblokir.
 * /ical/{token}.ics            → semua reservasi aktif
 * /ical/{token}.ics?exclude=airbnb → tanpa reservasi milik Airbnb (pasang link ini DI Airbnb)
 * Tidak memuat nama tamu / nominal.
 */
class IcalExportController extends Controller
{
    public function __invoke(string $token): Response
    {
        $villa = Villa::where('ical_token', $token)->where('is_active', true)->firstOrFail();
        $exclude = BookingPlatform::where('slug', (string) request('exclude'))->value('id');

        $bookings = Booking::query()
            ->where('villa_id', $villa->id)
            ->whereIn('status', BookingStatus::occupying())
            ->where('check_out', '>=', today()->subDays(30))
            ->when($exclude, fn ($q) => $q->where('platform_id', '!=', $exclude))
            ->orderBy('check_in')
            ->get();

        $calendar = Calendar::create($villa->name.' — PropertyMe')
            ->productIdentifier('-//PropertyMe//Villa Calendar//ID')
            ->refreshInterval(30)
            ->withoutTimezone();

        foreach ($bookings as $b) {
            $calendar->event(
                Event::create('Reserved')
                    ->uniqueIdentifier('pm-booking-'.$b->id.'@'.parse_url(config('app.url'), PHP_URL_HOST))
                    ->startsAt($b->check_in, withTime: false)
                    ->endsAt($b->check_out, withTime: false)
                    ->withoutTimezone()
            );
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="villa-'.$villa->id.'.ics"',
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }
}
