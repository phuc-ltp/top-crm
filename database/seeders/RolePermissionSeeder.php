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

        // Mỗi tổ chức có bộ vai trò riêng (spatie teams)
        foreach (\App\Models\Organization::pluck('id') as $orgId) {
            app(\App\Support\Tenancy\RoleProvisioner::class)->provision((int) $orgId);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
