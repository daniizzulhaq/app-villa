<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingPlatform extends Model
{
    public const AIRBNB = 'airbnb';
    public const BOOKING_COM = 'booking-com';
    public const DIRECT = 'direct';
    public const OTHER = 'lainnya';

    protected $fillable = ['name', 'slug', 'color', 'supports_ical', 'sort_order'];

    protected function casts(): array
    {
        return ['supports_ical' => 'boolean'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'platform_id');
    }

    /** Platform yang punya link iCal (Airbnb, Booking.com). */
    public static function icalPlatforms(): Collection
    {
        return static::query()->where('supports_ical', true)->orderBy('sort_order')->get();
    }
}
