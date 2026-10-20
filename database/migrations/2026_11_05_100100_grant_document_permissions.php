<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "documents.*": view (xem tài liệu được chia sẻ), create (tải lên), manage (quản trị mọi tài liệu kể cả riêng tư).
 * Gán theo ma trận mặc định của PermissionCatalog tại thời điểm này (ghi cứng). Không đổi quyền nào khác.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['documents.view', 'documents.create', 'documents.manage'];

    private const ALL_ROLES = [
        'Sales Manager', 'Sales Employee', 'Marketing Manager', 'Marketing Employee', 'Customer Support Manager', 'Support Agent',
        'Finance Manager', 'Accountant', 'Inventory Manager', 'Project Manager', 'Employee',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id', 'name');

        $grants = ['Organization Owner' => self::PERMISSIONS, 'Organization Administrator' => self::PERMISSIONS, 'Read-only Auditor' => ['documents.view']];
        foreach (self::ALL_ROLES as $role) {
            $grants[$role] = ['documents.view', 'documents.create'];
        }

        $rows = [];
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', array_keys($grants))->get(['id', 'name']) as $role) {
            foreach ($grants[$role->name] as $name) {
                $rows[] = ['permission_id' => $ids[$name], 'role_id' => $role->id];
            }
        }
        DB::table('role_has_permissions')->insertOrIgnore($rows);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
