<?php

namespace App\Notifications;

use Illuminate\Support\Collection;

/** Ke Staff: jadwal check-in/check-out besok di villa yang ditugaskan. */
class TomorrowScheduleNotification extends AppNotification
{
    /** @param Collection<int, \App\Models\Booking> $checkIns  @param Collection<int, \App\Models\Booking> $checkOuts */
    public function __construct(public Collection $checkIns, public Collection $checkOuts, public string $date) {}

    protected function title(): string
    {
        return sprintf(__('Besok: %d check-in, %d check-out'), $this->checkIns->count(), $this->checkOuts->count());
    }

    protected function body(): string
    {
        $lines = [];
        foreach ($this->checkIns as $b) {
            $lines[] = __('Check-in').' '.$b->villa->name.($b->guest_name ? ' — '.$b->guest_name : '').($b->arrival_time ? ' '.__('jam :time', ['time' => substr($b->arrival_time, 0, 5)]) : '');
        }
        foreach ($this->checkOuts as $b) {
            $lines[] = __('Check-out').' '.$b->villa->name.($b->guest_name ? ' — '.$b->guest_name : '');
        }

        return implode('; ', $lines).'.';
    }

    protected function url(): string
    {
        return route('staff.calendar', ['date' => $this->date]);
    }

    protected function icon(): string
    {
        return 'bi-calendar-event';
    }

    protected function actionText(): string
    {
        return __('Lihat kalender');
    }
}
