<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent RBAC seeder.
 *
 * The single source of truth is config/permissions.php — every permission
 * name and role assignment lives there. Re-running this seeder is safe:
 * it creates missing permissions/roles and syncs role assignments without
 * wiping anything.
 *
 * After running: php artisan db:seed --class=RolesAndPermissionsSeeder
 * the Spatie permission cache is automatically reset.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Bust Spatie's in-memory / Redis cache before any DB work so the
        //    registrar picks up our fresh rows immediately.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Flatten the grouped permission list and upsert every entry.
        $groups = config('permissions.permissions', []);

        $allPermissionNames = [];

        foreach ($groups as $permissions) {
            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $allPermissionNames[] = $name;
            }
        }

        // 3. Upsert roles and sync their permission assignments.
        $roleAssignments = config('permissions.roles', []);

        foreach ($roleAssignments as $roleName => $permissionList) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($permissionList === '*') {
                // Grant every seeded permission (super_admin pattern).
                $role->syncPermissions(Permission::where('guard_name', 'web')->get());
            } else {
                $role->syncPermissions($permissionList);
            }
        }

        // 4. Bust cache again so the application immediately sees the new rows
        //    without needing a separate `php artisan permission:cache-reset`.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            sprintf(
                'RBAC seeded: %d permissions across %d roles.',
                count($allPermissionNames),
                count($roleAssignments)
            )
        );
    }
}
