<?php

namespace App\Support\Tenancy;

use App\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tạo bộ vai trò mặc định cho một tổ chức (idempotent). Mỗi tổ chức sở hữu bản vai trò riêng,
 * nên chỉnh quyền ở tổ chức này không ảnh hưởng tổ chức khác.
 */
class RoleProvisioner
{
    public function provision(int $organizationId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(CurrentOrganization::class)->run($organizationId, function () use ($organizationId) {
            foreach (PermissionCatalog::ORG_ROLES as $name) {
                $role = Role::query()->where('organization_id', $organizationId)->where('name', $name)->where('guard_name', 'web')->first()
                    ?? Role::create(['name' => $name, 'guard_name' => 'web', 'organization_id' => $organizationId]);
                // Chỉ gán quyền mặc định khi vai trò chưa được tùy chỉnh
                if ($role->permissions()->count() === 0) {
                    $role->syncPermissions(PermissionCatalog::defaultsFor($name));
                }
            }
        });

        $registrar->forgetCachedPermissions();
    }
}
