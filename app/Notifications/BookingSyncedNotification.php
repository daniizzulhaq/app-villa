<?php

namespace App\Notifications;

use App\Models\BookingPlatform;
use App\Models\Villa;

/** Ke Manager: reservasi baru / batal dari sinkron iCal. */
class BookingSyncedNotification extends AppNotification
{
    public function __construct(
        public Villa $villa,
        public BookingPlatform $platform,
        public array $createdIds,
        public array $cancelledIds,
    ) {}

    protected function title(): string
    {
        $parts = [];
        if ($this->createdIds) {
            $parts[] = __(':count reservasi baru', ['count' => count($this->createdIds)]);
        }
        if ($this->cancelledIds) {
            $parts[] = count($this->cancelledIds).' dibatalkan';
        }

        return ucfirst(implode(', ', $parts)).' — '.$this->villa->name;
    }

    protected function body(): string
    {
        $txt = "Sinkron {$this->platform->name} untuk {$this->villa->name}: ";
        $txt .= $this->createdIds ? __(':count reservasi baru perlu dilengkapi (nama tamu & nominal).', ['count' => count($this->createdIds)]).' ' : '';
        $txt .= $this->cancelledIds ? __(':count reservasi hilang dari kalender platform dan ditandai Cancelled.', ['count' => count($this->cancelledIds)]) : '';

        return trim($txt);
    }

    protected function url(): string
    {
        return route('bookings.index', ['villa' => $this->villa->id, 'status' => $this->createdIds ? 'needs_completion' : 'cancelled']);
    }

    protected function icon(): string
    {
        return $this->createdIds ? 'bi-calendar-plus' : 'bi-calendar-x';
    }

    protected function tone(): string
    {
        return $this->createdIds ? 'info' : 'warning';
    }

    protected function actionText(): string
    {
        return __('Lihat reservasi');
    }
}
