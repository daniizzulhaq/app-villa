<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\TomorrowScheduleNotification;
use App\Support\Notify;
use Illuminate\Console\Command;

class SendTomorrowScheduleCommand extends Command
{
    protected $signature = 'propertyme:notify-tomorrow';

    protected $description = 'Kirim jadwal check-in/check-out besok ke Staff yang bertugas';

    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();
        $sent = 0;

        User::role(UserRole::Staff->value)->active()->with('assignedVillas:id')->each(function (User $staff) use ($tomorrow, &$sent) {
            $villaIds = $staff->assignedVillas->pluck('id');
            if ($villaIds->isEmpty()) {
                return;
            }
            $base = Booking::query()->with('villa:id,name')->whereIn('villa_id', $villaIds)
                ->where('status', '!=', BookingStatus::Cancelled->value);
            $in = (clone $base)->whereDate('check_in', $tomorrow)->orderBy('arrival_time')->get();
            $out = (clone $base)->whereDate('check_out', $tomorrow)->get();

            if ($in->isNotEmpty() || $out->isNotEmpty()) {
                Notify::send([$staff], new TomorrowScheduleNotification($in, $out, $tomorrow));
                $sent++;
            }
        });

        $this->info("Pengingat dikirim ke {$sent} staff.");

        return self::SUCCESS;
    }
}
