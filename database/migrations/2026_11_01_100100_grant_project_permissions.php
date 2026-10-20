<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "projects.*" (dự án). Gán cho các vai trò ở MỌI tổ chức theo đúng ma trận mặc định
 * của PermissionCatalog tại thời điểm này (ghi cứng để migration không đổi theo code sau này).
 * Không đổi quyền nào khác.
 */
return new class extends Migration
{
    private const ACTIONS = ['view', 'view_all', 'create', 'update', 'delete', 'export'];

    private const GRANTS = [
        'Organization Owner' => self::ACTIONS,
        'Organization Administrator' => self::ACTIONS,
        'Project Manager' => self::ACTIONS,
        'Sales Manager' => ['view', 'create', 'update'],
        'Marketing Manager' => ['view', 'create', 'update'],
        'Customer Support Manager' => ['view', 'create', 'update'],
        'Finance Manager' => ['view'],
        'Accountant' => ['view'],
        'Inventory Manager' => ['view'],
        'Sales Employee' => ['view'],
        'Marketing Employee' => ['view'],
        'Support Agent' => ['view'],
        'Employee' => ['view'],
        'Read-only Auditor' => ['view', 'view_all'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::ACTIONS as $action) {
            DB::table('permissions')->insertOrIgnore(['name' => "projects.$action", 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', 'projects.%')->pluck('id', 'name');

        $rows = [];
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', array_keys(self::GRANTS))->get(['id', 'name']) as $role) {
            foreach (self::GRANTS[$role->name] as $action) {
                $rows[] = ['permission_id' => $ids["projects.$action"], 'role_id' => $role->id];
            }
        }
        DB::table('role_has_permissions')->insertOrIgnore($rows);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', 'projects.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
