<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingPlatform;
use App\Models\VillaChannel;
use App\Notifications\BookingSyncedNotification;
use App\Services\IcalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function platform(string $slug): int
    {
        return BookingPlatform::where('slug', $slug)->value('id');
    }

    private function payload(int $villaId, string $in, string $out, array $extra = []): array
    {
        return $extra + ['villa_id' => $villaId, 'platform_id' => $this->platform('direct'), 'check_in' => $in, 'check_out' => $out,
            'guest_name' => 'Budi', 'guest_count' => 2, 'status' => 'confirmed'];
    }

    public function test_manager_creates_booking_and_overlap_is_rejected_but_turnover_allowed(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();

        $this->actingAs($manager)->post(route('bookings.store'), $this->payload($villa->id, '2026-12-10', '2026-12-14'))->assertRedirect();
        $this->assertSame(1, Booking::count());

        // Bentrok
        $this->actingAs($manager)->post(route('bookings.store'), $this->payload($villa->id, '2026-12-12', '2026-12-16'))
            ->assertSessionHasErrors('check_in');
        // Check-in di hari check-out tamu sebelumnya → boleh
        $this->actingAs($manager)->post(route('bookings.store'), $this->payload($villa->id, '2026-12-14', '2026-12-16'))->assertSessionHasNoErrors();
        // Cancelled tidak memblokir
        Booking::first()->update(['status' => BookingStatus::Cancelled]);
        $this->actingAs($manager)->post(route('bookings.store'), $this->payload($villa->id, '2026-12-10', '2026-12-13'))->assertSessionHasNoErrors();

        $this->assertSame(3, Booking::count());
    }

    public function test_check_out_must_be_after_check_in(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();

        $this->actingAs($manager)->post(route('bookings.store'), $this->payload($villa->id, '2026-12-10', '2026-12-10'))
            ->assertSessionHasErrors(['check_out' => 'Tanggal check-out harus setelah check-in (minimal 1 malam).']);
    }

    public function test_staff_cannot_modify_bookings_and_sees_only_assigned_villas_without_money(): void
    {
        $staff = $this->makeUser(UserRole::Staff);
        $mine = $this->makeVilla();
        $other = $this->makeVilla();
        $staff->assignedVillas()->attach($mine);

        $b1 = Booking::create(['villa_id' => $mine->id, 'platform_id' => $this->platform('airbnb'), 'check_in' => today(), 'check_out' => today()->addDays(2), 'status' => 'confirmed', 'guest_name' => 'Tamu A', 'arrival_time' => '14:00']);
        Booking::create(['villa_id' => $other->id, 'platform_id' => $this->platform('airbnb'), 'check_in' => today(), 'check_out' => today()->addDays(2), 'status' => 'confirmed', 'guest_name' => 'Tamu B']);

        $this->actingAs($staff)->post(route('bookings.store'), $this->payload($mine->id, '2026-12-01', '2026-12-02'))->assertForbidden();
        $this->actingAs($staff)->put(route('bookings.update', $b1), $this->payload($mine->id, '2026-12-01', '2026-12-02'))->assertForbidden();
        $this->actingAs($staff)->get(route('bookings.index'))->assertForbidden();

        $json = $this->actingAs($staff)->getJson(route('bookings.events', ['start' => today()->subDay()->toDateString(), 'end' => today()->addWeek()->toDateString()]))
            ->assertOk()->json();
        $this->assertCount(1, $json);
        $this->assertStringContainsString('Tamu A', $json[0]['title']);
        $this->assertNull($json[0]['extendedProps']['url']);
        $this->assertStringNotContainsString('gross', json_encode($json));

        $this->actingAs($staff)->get(route('staff.calendar'))->assertOk()->assertSee('Hari ini')->assertSee('Tamu A')->assertSee('14:00')->assertDontSee('Tamu B');
    }

    public function test_owner_and_manager_pages_render(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla(['owner_id' => $owner->id]);
        $b = Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('airbnb'), 'check_in' => today(), 'check_out' => today()->addDay(), 'status' => 'needs_completion', 'source' => 'ical', 'ical_uid' => 'x1']);

        foreach ([$owner, $manager] as $u) {
            $this->actingAs($u)->get(route('bookings.calendar'))->assertOk();
            $this->actingAs($u)->get(route('bookings.index'))->assertOk();
            $this->actingAs($u)->get(route('bookings.show', $b))->assertOk();
        }
        $this->actingAs($manager)->get(route('bookings.edit', $b))->assertOk()->assertSee('tidak bisa diubah di sini');
        $this->actingAs($manager)->get(route('bookings.create'))->assertOk();
        $this->actingAs($manager)->get(route('sync.index'))->assertOk();
        $this->actingAs($owner)->get(route('bookings.edit', $b))->assertForbidden();
    }

    public function test_ical_booking_dates_cannot_be_changed_manually(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $b = Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('airbnb'), 'check_in' => '2026-12-01', 'check_out' => '2026-12-03', 'status' => 'needs_completion', 'source' => 'ical', 'ical_uid' => 'u1']);

        $this->actingAs($manager)->put(route('bookings.update', $b), ['check_in' => '2026-12-20', 'check_out' => '2026-12-25', 'guest_name' => 'Sarah', 'guest_count' => 3, 'status' => 'confirmed'])
            ->assertSessionHasNoErrors();

        $b->refresh();
        $this->assertSame('2026-12-01', $b->check_in->toDateString());
        $this->assertSame('Sarah', $b->guest_name);
        $this->assertSame(BookingStatus::Confirmed, $b->status);
    }

    private function ics(array $events): string
    {
        $body = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Airbnb Inc//Hosting Calendar//EN\r\n";
        foreach ($events as [$uid, $in, $out, $summary, $desc]) {
            $body .= "BEGIN:VEVENT\r\nDTSTART;VALUE=DATE:{$in}\r\nDTEND;VALUE=DATE:{$out}\r\nUID:{$uid}\r\nSUMMARY:{$summary}\r\nDESCRIPTION:{$desc}\r\nEND:VEVENT\r\n";
        }

        return $body."END:VCALENDAR\r\n";
    }

    public function test_ical_sync_creates_updates_cancels_and_guards_empty_feed(): void
    {
        Notification::fake();
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $channel = VillaChannel::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('airbnb'), 'ical_url' => 'https://www.airbnb.com/calendar/ical/1.ics']);

        $in1 = today()->addDays(5)->format('Ymd');
        $out1 = today()->addDays(8)->format('Ymd');
        $in2 = today()->addDays(10)->format('Ymd');
        $out2 = today()->addDays(12)->format('Ymd');

        Http::fakeSequence()
            ->push($this->ics([
                ['a1@airbnb.com', $in1, $out1, 'Reserved', 'Reservation URL: https://www.airbnb.com/hosting/reservations/details/HMABCDE123\\nPhone'],
                ['a2@airbnb.com', $in2, $out2, 'Reserved', ''],
                ['blk@airbnb.com', $in2, $out2, 'Airbnb (Not available)', ''],
            ]))
            ->push($this->ics([['a1@airbnb.com', $in1, $out1, 'Reserved', '']]))
            ->push($this->ics([]))
            ->push('Not found', 404);

        $sync = app(IcalSyncService::class);

        $r = $sync->syncChannel($channel);
        $this->assertNull($r['error']);
        $this->assertSame(2, $r['created'], 'Blokir "Not available" bukan reservasi');
        $b1 = Booking::where('ical_uid', 'a1@airbnb.com')->first();
        $this->assertSame(BookingStatus::NeedsCompletion, $b1->status);
        $this->assertSame('HMABCDE123', $b1->booking_ref);
        Notification::assertSentTo($manager, BookingSyncedNotification::class);

        // a2 hilang dari feed → Cancelled
        $r = $sync->syncChannel($channel->fresh());
        $this->assertSame(1, $r['cancelled']);
        $this->assertSame(BookingStatus::Cancelled, Booking::where('ical_uid', 'a2@airbnb.com')->first()->status);

        // Feed kosong: hanya 1 booking mendatang tersisa → tetap dibatalkan (ambang pengaman 2)
        $r = $sync->syncChannel($channel->fresh());
        $this->assertSame(1, $r['cancelled']);

        // Error HTTP → pesan ramah, tidak crash
        $r = $sync->syncChannel($channel->fresh());
        $this->assertStringContainsString('404', $r['error']);
        $this->assertNotNull($channel->fresh()->last_error);
    }

    public function test_empty_feed_with_many_future_bookings_does_not_mass_cancel(): void
    {
        $villa = $this->makeVilla();
        $channel = VillaChannel::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('airbnb'), 'ical_url' => 'https://x.test/a.ics']);
        foreach ([1, 2, 3] as $i) {
            Booking::create(['villa_id' => $villa->id, 'platform_id' => $channel->platform_id, 'source' => 'ical', 'ical_uid' => "u{$i}",
                'check_in' => today()->addDays($i * 3), 'check_out' => today()->addDays($i * 3 + 2), 'status' => 'confirmed']);
        }
        Http::fake(['*' => Http::response($this->ics([]))]);

        $r = app(IcalSyncService::class)->syncChannel($channel);

        $this->assertSame(0, $r['cancelled']);
        $this->assertNotNull($r['warning']);
        $this->assertSame(0, Booking::where('status', 'cancelled')->count());
    }

    public function test_sync_now_button_and_ical_export(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('direct'), 'check_in' => today()->addDays(3), 'check_out' => today()->addDays(5), 'status' => 'confirmed', 'guest_name' => 'Rahasia']);
        Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('airbnb'), 'check_in' => today()->addDays(7), 'check_out' => today()->addDays(9), 'status' => 'confirmed']);

        $this->actingAs($manager)->post(route('sync.run'))->assertRedirect()->assertSessionHas('warning');

        $ics = $this->get(route('ical.export', ['token' => $villa->ical_token]))->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringNotContainsString('Rahasia', $ics, 'Nama tamu tidak bocor');

        $forAirbnb = $this->get(route('ical.export', ['token' => $villa->ical_token, 'exclude' => 'airbnb']))->getContent();
        $this->assertSame(1, substr_count($forAirbnb, 'BEGIN:VEVENT'));

        $this->get('/ical/'.str_repeat('x', 48).'.ics')->assertNotFound();
    }

    public function test_statuses_advance_automatically(): void
    {
        $villa = $this->makeVilla();
        $b = Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('direct'), 'check_in' => today()->subDay(), 'check_out' => today()->addDay(), 'status' => 'confirmed']);
        $done = Booking::create(['villa_id' => $villa->id, 'platform_id' => $this->platform('direct'), 'check_in' => today()->subDays(5), 'check_out' => today()->subDays(2), 'status' => 'confirmed']);

        $this->artisan('propertyme:advance-bookings')->assertSuccessful();

        $this->assertSame(BookingStatus::CheckedIn, $b->fresh()->status);
        $this->assertSame(BookingStatus::CheckedOut, $done->fresh()->status);
    }
}
