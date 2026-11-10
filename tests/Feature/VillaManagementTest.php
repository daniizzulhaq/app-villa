<?php

namespace Tests\Feature;

use App\Enums\FeeBasis;
use App\Enums\UserRole;
use App\Models\BookingPlatform;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VillaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function payload(array $override = []): array
    {
        return $override + [
            'name' => 'Villa Kayu Manis',
            'location' => 'Ubud',
            'fee_percentage' => '15',
            'fee_basis' => FeeBasis::NetOfPlatform->value,
            'fee_effective_from' => '2026-01-01',
            'started_at' => '2026-01-01',
            'timezone' => 'Asia/Makassar',
            'is_active' => '1',
        ];
    }

    public function test_manager_creates_villa_with_owner_staff_channels_and_first_fee_history(): void
    {
        Storage::fake('public');
        $manager = $this->makeUser(UserRole::Master);
        $owner = $this->makeUser(UserRole::Owner);
        $staff = $this->makeUser(UserRole::Staff);

        $this->actingAs($manager)->post(route('villas.store'), $this->payload([
            'owner_id' => $owner->id,
            'staff_ids' => [$staff->id],
            'photo' => UploadedFile::fake()->image('villa.jpg', 3000, 2000),
            'channels' => ['airbnb' => 'https://www.airbnb.com/calendar/ical/123.ics', 'booking-com' => ''],
        ]))->assertRedirect()->assertSessionHas('success');

        $villa = Villa::firstOrFail();
        $this->assertSame($owner->id, $villa->owner_id);
        $this->assertTrue($villa->staff->contains($staff));
        $this->assertCount(1, $villa->channels);
        $this->assertNotEmpty($villa->ical_token);
        $this->assertCount(1, $villa->feeHistories);
        $this->assertSame('15.00', (string) $villa->feeHistories->first()->fee_percentage);
        Storage::disk('public')->assertExists($villa->photo);
    }

    public function test_changing_fee_records_history_and_old_fee_stays_applicable_to_past_dates(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $this->actingAs($manager)->post(route('villas.store'), $this->payload());
        $villa = Villa::firstOrFail();

        $this->actingAs($manager)->put(route('villas.update', $villa), $this->payload([
            'fee_percentage' => '12,5',  // format Indonesia
            'fee_basis' => FeeBasis::Gross->value,
            'fee_effective_from' => '2026-06-01',
        ]))->assertRedirect();

        $villa->refresh();
        $this->assertSame('12.50', (string) $villa->fee_percentage);
        $this->assertCount(2, $villa->feeHistories);
        $this->assertSame('15.00', (string) $villa->feeOn('2026-05-31')->fee_percentage);
        $this->assertSame('12.50', (string) $villa->feeOn('2026-06-01')->fee_percentage);
    }

    public function test_other_ical_link_renders_and_can_be_saved_changed_and_removed(): void
    {
        $manager = $this->makeUser(UserRole::Master, ['locale' => 'id']);
        $channels = [
            'airbnb' => 'https://www.airbnb.com/calendar/ical/123.ics',
            'booking-com' => 'https://ical.booking.com/v1/export?t=example',
            'lainnya' => 'https://calendar.example.com/villa.ics?token=example',
        ];

        $this->actingAs($manager)->get(route('villas.create'))->assertOk()
            ->assertSeeInOrder(['channels[airbnb]', 'channels[booking-com]', 'channels[lainnya]'], false)
            ->assertSee('Link iCal lainnya');

        $this->post(route('villas.store'), $this->payload(['channels' => $channels]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $villa = Villa::firstOrFail();
        $other = $villa->channels()->where('platform_id', BookingPlatform::where('slug', 'lainnya')->value('id'))->firstOrFail();
        $this->assertSame($channels['lainnya'], $other->ical_url);
        $this->assertCount(3, $villa->channels);
        $this->get(route('villas.edit', $villa))->assertOk()->assertSee($channels['lainnya']);

        $other->update(['last_error' => 'Old error', 'last_synced_at' => now(), 'last_success_at' => now()]);
        $channels['lainnya'] = 'https://calendar.example.com/replacement.ics';
        $this->put(route('villas.update', $villa), $this->payload(['channels' => $channels]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $other->refresh();
        $this->assertSame($channels['lainnya'], $other->ical_url);
        $this->assertNull($other->last_error);
        $this->assertNull($other->last_synced_at);
        $this->assertNull($other->last_success_at);

        $channels['lainnya'] = '';
        $this->put(route('villas.update', $villa), $this->payload(['channels' => $channels]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('villa_channels', ['id' => $other->id]);
        $this->assertEqualsCanonicalizing(
            [$channels['airbnb'], $channels['booking-com']],
            $villa->channels()->pluck('ical_url')->all(),
        );

        $manager->update(['locale' => 'en']);
        $this->get(route('villas.edit', $villa))->assertOk()->assertSee('Other iCal link')
            ->assertSee('Optional: one iCal export link');
    }

    public function test_other_ical_link_rejects_invalid_urls(): void
    {
        $this->actingAs($this->makeUser(UserRole::Master));

        foreach (['not-a-url', 'ftp://calendar.example.com/villa.ics', 'https://calendar.example.com/'.str_repeat('a', 2000)] as $url) {
            $this->post(route('villas.store'), $this->payload(['channels' => ['lainnya' => $url]]))
                ->assertSessionHasErrors('channels.lainnya');
        }

        $this->assertSame(0, Villa::count());
    }

    public function test_upgrade_enables_other_ical_without_replacing_existing_platform_or_channels(): void
    {
        $platform = BookingPlatform::where('slug', 'lainnya')->firstOrFail();
        $platform->update(['supports_ical' => false, 'color' => '#123456']);
        $channel = $this->makeVilla()->channels()->create([
            'platform_id' => $platform->id,
            'ical_url' => 'https://calendar.example.com/villa.ics',
        ]);
        $migration = require database_path('migrations/2026_11_10_100001_enable_ical_for_other_booking_platform.php');

        $migration->up();
        $migration->up(); // Aman jika flag sudah diaktifkan lewat phpMyAdmin.

        $this->assertTrue($platform->fresh()->supports_ical);
        $this->assertSame('#123456', $platform->fresh()->color);
        $this->assertSame($platform->id, $channel->fresh()->platform_id);
        $this->assertSame(1, BookingPlatform::where('slug', 'lainnya')->count());

        $migration->down();
        $this->assertFalse($platform->fresh()->supports_ical);
        $this->assertNotNull($channel->fresh());
    }

    public function test_update_without_fee_change_does_not_add_history(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $this->actingAs($manager)->post(route('villas.store'), $this->payload());
        $villa = Villa::firstOrFail();

        $this->actingAs($manager)->put(route('villas.update', $villa), $this->payload(['name' => 'Nama Baru']));

        $this->assertCount(1, $villa->fresh()->feeHistories);
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $manager = $this->makeUser(UserRole::Master);

        $this->actingAs($manager)->post(route('villas.store'), $this->payload([
            'name' => '', 'fee_percentage' => '150', 'fee_basis' => 'custom', 'fee_basis_note' => '',
        ]))->assertSessionHasErrors([
            'name' => 'Nama villa wajib diisi.',
            'fee_percentage' => 'Persentase management fee maksimal 100.',
            'fee_basis_note' => 'Jelaskan basis fee yang disepakati dengan owner.',
        ]);
    }

    public function test_owner_id_must_belong_to_owner_role(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);

        $this->actingAs($manager)->post(route('villas.store'), $this->payload(['owner_id' => $staff->id]))
            ->assertSessionHasErrors('owner_id');
    }

    public function test_staff_and_owner_cannot_access_villa_management(): void
    {
        $villa = $this->makeVilla();

        foreach ([UserRole::Staff, UserRole::Owner] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user)->get(route('villas.index'))->assertForbidden();
            $this->actingAs($user)->get(route('villas.create'))->assertForbidden();
            $this->actingAs($user)->post(route('villas.store'), $this->payload())->assertForbidden();
            $this->actingAs($user)->put(route('villas.update', $villa), $this->payload())->assertForbidden();
            $this->actingAs($user)->delete(route('villas.destroy', $villa))->assertForbidden();
        }
        $this->assertSame(1, Villa::count());
    }

    public function test_manager_pages_render(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();
        $villa->feeHistories()->create(['fee_percentage' => 15, 'fee_basis' => 'net_of_platform', 'effective_from' => '2026-01-01']);

        $this->actingAs($manager)->get(route('villas.index'))->assertOk()->assertSee($villa->name);
        $this->actingAs($manager)->get(route('villas.create'))->assertOk();
        $this->actingAs($manager)->get(route('villas.show', $villa))->assertOk()->assertSee('Riwayat perubahan fee');
        $this->actingAs($manager)->get(route('villas.edit', $villa))->assertOk();
    }

    public function test_manager_can_delete_empty_villa(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $villa = $this->makeVilla();

        $this->actingAs($manager)->delete(route('villas.destroy', $villa))->assertRedirect(route('villas.index'));
        $this->assertSoftDeleted($villa);
    }
}
