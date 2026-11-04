<?php

namespace Tests;

use App\Enums\FeeBasis;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Villa;
use Database\Seeders\BookingPlatformSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedBase(): void
    {
        $this->seed([RoleSeeder::class, BookingPlatformSeeder::class, ExpenseCategorySeeder::class]);
    }

    protected function makeUser(UserRole $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['is_active' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function makeVilla(array $attrs = []): Villa
    {
        return Villa::create($attrs + [
            'name' => 'Villa Uji '.uniqid(),
            'fee_percentage' => 15,
            'fee_basis' => FeeBasis::NetOfPlatform,
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);
    }
}
