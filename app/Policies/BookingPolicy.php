<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // hasil query selalu dibatasi Villa::accessibleBy()
    }

    /** Semua role boleh melihat booking di villa yang bisa diaksesnya. */
    public function view(User $user, Booking $booking): bool
    {
        return $user->canAccessVilla($booking->villa_id);
    }

    /** Nominal pendapatan booking: tidak untuk Staff. */
    public function viewRevenue(User $user, Booking $booking): bool
    {
        return ! $user->isStaff() && $user->canAccessVilla($booking->villa_id);
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Hanya Master / Manager wilayah villa itu. Staff & Owner tidak dapat mengubah data reservasi. */
    public function update(User $user, Booking $booking): bool
    {
        return $user->isManager() && $user->canAccessVilla($booking->villa_id);
    }

    /** Tanggal booking dari sinkron iCal milik platform — tidak diubah manual. */
    public function changeDates(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking) && ! $booking->isFromPlatformSync();
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking) && ! $booking->isFromPlatformSync();
    }

    public function sync(User $user): bool
    {
        return $user->isManager();
    }
}
