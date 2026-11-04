<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role: master, manager (wilayah), owner, staff. Aturan detail (villa mana, status apa, bulan terkunci) ada di Policy,
 * karena bergantung pada data — tidak cukup diwakili permission statis.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
