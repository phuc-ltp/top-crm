<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** An toàn khi chạy lại nhiều lần (idempotent). */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (PermissionCatalog::ROLES as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            // Chỉ gán quyền mặc định khi vai trò chưa được tùy chỉnh
            if ($role->permissions()->count() === 0) {
                $role->syncPermissions(PermissionCatalog::defaultsFor($roleName));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
