<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    public function test_manager_creates_staff_assigned_to_multiple_villas(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $v1 = $this->makeVilla();
        $v2 = $this->makeVilla();

        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'Made', 'email' => 'MADE@Example.com', 'phone' => '0812 3456 789',
            'role' => 'staff', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'is_active' => '1', 'villa_ids' => [$v1->id, $v2->id],
        ])->assertRedirect(route('users.index'));

        $staff = User::where('email', 'made@example.com')->firstOrFail();
        $this->assertTrue($staff->isStaff());
        $this->assertEqualsCanonicalizing([$v1->id, $v2->id], $staff->accessibleVillaIds());
    }

    public function test_manager_creates_owner_and_owner_takes_villa_ownership(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $oldOwner = $this->makeUser(UserRole::Owner);
        $villa = $this->makeVilla(['owner_id' => $oldOwner->id]);

        $this->actingAs($manager)->post(route('users.store'), [
            'name' => 'Sarah', 'email' => 'sarah@example.com', 'role' => 'owner',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'is_active' => '1',
            'villa_ids' => [$villa->id],
        ])->assertRedirect();

        $owner = User::where('email', 'sarah@example.com')->firstOrFail();
        $this->assertSame($owner->id, $villa->fresh()->owner_id);
    }

    public function test_changing_role_from_staff_removes_assignments(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);
        $villa = $this->makeVilla();
        $staff->assignedVillas()->attach($villa);

        $this->actingAs($manager)->put(route('users.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email, 'role' => 'manager', 'is_active' => '1',
        ])->assertRedirect();

        $staff->refresh();
        $this->assertTrue($staff->isManager());
        $this->assertCount(0, $staff->assignedVillas);
    }

    public function test_manager_cannot_demote_or_deactivate_self(): void
    {
        $manager = $this->makeUser(UserRole::Master);

        $this->actingAs($manager)->put(route('users.update', $manager), [
            'name' => $manager->name, 'email' => $manager->email, 'role' => 'staff', 'is_active' => '0',
        ])->assertSessionHasErrors(['role', 'is_active']);

        $this->actingAs($manager)->patch(route('users.toggle', $manager))->assertForbidden();
        $this->actingAs($manager)->delete(route('users.destroy', $manager))->assertForbidden();
    }

    public function test_toggle_and_soft_delete_user(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);

        $this->actingAs($manager)->patch(route('users.toggle', $staff))->assertRedirect();
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($manager)->delete(route('users.destroy', $staff))->assertRedirect();
        $this->assertSoftDeleted($staff);
    }

    public function test_staff_and_owner_cannot_manage_users(): void
    {
        $target = $this->makeUser(UserRole::Staff);

        foreach ([UserRole::Staff, UserRole::Owner] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
            $this->actingAs($user)->post(route('users.store'), ['name' => 'x'])->assertForbidden();
            $this->actingAs($user)->patch(route('users.toggle', $target))->assertForbidden();
        }
    }

    public function test_user_pages_render(): void
    {
        $manager = $this->makeUser(UserRole::Master);
        $staff = $this->makeUser(UserRole::Staff);
        $this->makeVilla();

        $this->actingAs($manager)->get(route('users.index'))->assertOk()->assertSee($staff->name);
        $this->actingAs($manager)->get(route('users.create', ['role' => 'owner']))->assertOk();
        $this->actingAs($manager)->get(route('users.edit', $staff))->assertOk();
    }

    public function test_any_user_can_update_own_profile_and_password(): void
    {
        $staff = $this->makeUser(UserRole::Staff, ['password' => 'lama12345']);

        $this->actingAs($staff)->get(route('profile.edit'))->assertOk();
        $this->actingAs($staff)->put(route('profile.update'), ['name' => 'Nama Baru', 'email' => $staff->email, 'phone' => '0812 3456 7890'])
            ->assertSessionHasNoErrors();
        $this->actingAs($staff)->put(route('profile.password'), [
            'current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($staff)->put(route('profile.password'), [
            'current_password' => 'lama12345', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $staff->fresh()->name);
    }
}
