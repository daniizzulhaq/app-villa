<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Region;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VillaAssignmentLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->master = $this->makeUser(UserRole::Master);
    }

    public static function villaRoles(): array
    {
        return [['owner'], ['staff']];
    }

    private function villas(int $count, array $attributes = []): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->makeVilla($attributes)->id;
        }

        return $ids;
    }

    private function account(User $user, string $role, array $extra = []): array
    {
        return $extra + ['name' => $user->name, 'email' => $user->email, 'role' => $role, 'is_active' => '1'];
    }

    private function villaPayload(array $extra = []): array
    {
        return $extra + ['name' => 'Villa tambahan', 'fee_percentage' => 15, 'fee_basis' => 'net_of_platform',
            'timezone' => 'Asia/Makassar', 'is_active' => '1'];
    }

    #[DataProvider('villaRoles')]
    public function test_owner_and_staff_accept_twenty_and_reject_twenty_one_on_account_form(string $role): void
    {
        $ids = $this->villas(21);
        $user = $this->makeUser(UserRole::from($role));
        $this->actingAs($this->master)->put(route('users.update', $user),
            $this->account($user, $role, ['villa_ids' => array_slice($ids, 0, 20)]))->assertSessionHasNoErrors();
        $this->assertCount(20, $user->fresh()->accessibleVillaIds());
        $this->put(route('users.update', $user), $this->account($user, $role, ['villa_ids' => $ids]))
            ->assertSessionHasErrors('villa_ids');
        $this->assertCount(20, $user->fresh()->accessibleVillaIds());
    }

    #[DataProvider('villaRoles')]
    public function test_oversized_new_account_is_rolled_back(string $role): void
    {
        $ids = $this->villas(21);
        $this->actingAs($this->master)->post(route('users.store'), [
            'name' => 'Akun melebihi batas', 'email' => 'over@example.test', 'role' => $role,
            'password' => 'password123', 'password_confirmation' => 'password123', 'is_active' => '1', 'villa_ids' => $ids,
        ])->assertSessionHasErrors('villa_ids');
        $this->assertDatabaseMissing('users', ['email' => 'over@example.test']);
        $this->assertSame(0, Villa::whereNotNull('owner_id')->count());
        $this->assertDatabaseCount('villa_user', 0);
    }

    public function test_manager_limit_counts_villas_across_all_selected_regions(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $a = Region::create(['name' => 'Wilayah A']);
        $b = Region::create(['name' => 'Wilayah B']);
        $c = Region::create(['name' => 'Wilayah C']);
        $this->villas(10, ['region_id' => $a->id]);
        $this->villas(10, ['region_id' => $b->id]);
        $this->villas(1, ['region_id' => $c->id]);
        $this->actingAs($this->master)->put(route('users.update', $manager),
            $this->account($manager, 'manager', ['region_ids' => [$a->id, $b->id]]))->assertSessionHasNoErrors();
        $this->assertCount(20, $manager->fresh()->accessibleVillaIds());
        $this->put(route('users.update', $manager),
            $this->account($manager, 'manager', ['region_ids' => [$a->id, $b->id, $c->id]]))->assertSessionHasErrors('region_ids');
        $this->assertCount(20, $manager->fresh()->accessibleVillaIds());
        $this->assertCount(2, $manager->fresh()->regions);
    }

    #[DataProvider('villaRoles')]
    public function test_villa_form_cannot_add_twenty_first_villa_to_owner_or_staff(string $role): void
    {
        $user = $this->makeUser(UserRole::from($role));
        $ids = $this->villas(20, $role === 'owner' ? ['owner_id' => $user->id] : []);
        if ($role === 'staff') {
            $user->assignedVillas()->sync($ids);
        }
        $extra = $role === 'owner' ? ['owner_id' => $user->id] : ['staff_ids' => [$user->id]];
        $this->actingAs($this->master)->post(route('villas.store'), $this->villaPayload($extra))
            ->assertSessionHasErrors($role === 'owner' ? 'owner_id' : 'staff_ids');
        $this->assertDatabaseCount('villas', 20);
        $this->assertDatabaseCount('villa_fee_histories', 0);
        // Saving an existing assignment at the limit still succeeds.
        $this->put(route('villas.update', $ids[0]), $this->villaPayload($extra))->assertSessionHasNoErrors();
        $this->assertCount(20, $user->fresh()->accessibleVillaIds());
    }

    public function test_new_villa_cannot_overflow_manager_region(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $region = Region::create(['name' => 'Wilayah penuh']);
        $region->managers()->attach($manager);
        $this->villas(20, ['region_id' => $region->id]);
        $this->actingAs($this->master)->post(route('villas.store'), $this->villaPayload(['region_id' => $region->id]))
            ->assertSessionHasErrors('region_id');
        $this->assertDatabaseCount('villas', 20);
    }

    public function test_region_form_cannot_add_villas_beyond_manager_total_and_rolls_back(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $a = Region::create(['name' => 'Wilayah penuh']);
        $b = Region::create(['name' => 'Wilayah tambahan']);
        $a->managers()->attach($manager);
        $this->villas(20, ['region_id' => $a->id]);
        $extra = $this->makeVilla();
        $this->actingAs($this->master)->put(route('regions.update', $b), [
            'name' => 'Nama diubah', 'villa_ids' => [$extra->id], 'manager_ids' => [$manager->id],
        ])->assertSessionHasErrors('manager_ids');
        $this->assertSame('Wilayah tambahan', $b->fresh()->name);
        $this->assertNull($extra->fresh()->region_id);
        $this->assertCount(1, $manager->fresh()->regions);
    }

    public function test_moving_villas_between_regions_of_same_manager_does_not_double_count(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $a = Region::create(['name' => 'Wilayah A']);
        $b = Region::create(['name' => 'Wilayah B']);
        $manager->regions()->sync([$a->id, $b->id]);
        $ids = $this->villas(20, ['region_id' => $a->id]);
        $this->actingAs($this->master)->put(route('regions.update', $b), [
            'name' => $b->name, 'villa_ids' => [$ids[0]], 'manager_ids' => [$manager->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame($b->id, Villa::find($ids[0])->region_id);
        $this->assertCount(20, $manager->fresh()->accessibleVillaIds());
    }

    public function test_staff_assignments_in_other_regions_are_included_in_limit(): void
    {
        $manager = $this->makeUser(UserRole::Manager);
        $staff = $this->makeUser(UserRole::Staff);
        $region = Region::create(['name' => 'Wilayah Manager']);
        $region->managers()->attach($manager);
        $local = $this->villas(2, ['region_id' => $region->id]);
        $outside = $this->villas(19);
        $staff->assignedVillas()->sync(array_merge($outside, [$local[0]]));
        $this->actingAs($manager)->put(route('users.update', $staff),
            $this->account($staff, 'staff', ['villa_ids' => $local]))->assertSessionHasErrors('villa_ids');
        $this->assertCount(20, $staff->fresh()->accessibleVillaIds());
    }

    public function test_existing_excess_assignments_can_be_reduced_without_hiding_access(): void
    {
        $owner = $this->makeUser(UserRole::Owner);
        $ids = $this->villas(21, ['owner_id' => $owner->id]);
        $this->assertCount(21, $owner->fresh()->accessibleVillaIds());
        $this->actingAs($this->master)->put(route('users.update', $owner),
            $this->account($owner, 'owner', ['villa_ids' => array_slice($ids, 0, 20)]))->assertSessionHasNoErrors();
        $this->assertCount(20, $owner->fresh()->accessibleVillaIds());
    }
}
