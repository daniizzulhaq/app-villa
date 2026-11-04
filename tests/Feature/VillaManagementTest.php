<?php

namespace Tests\Feature;

use App\Enums\FeeBasis;
use App\Enums\UserRole;
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
