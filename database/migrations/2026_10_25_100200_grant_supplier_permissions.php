<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "suppliers.*" (nhà cung cấp). Gán cho các vai trò ở MỌI tổ chức theo đúng ma trận mặc định
 * của PermissionCatalog tại thời điểm này (ghi cứng để migration không đổi theo code sau này).
 * Không đổi quyền nào khác.
 */
return new class extends Migration
{
    private const ACTIONS = ['view', 'create', 'update', 'delete', 'export'];

    private const GRANTS = [
        'Organization Owner' => self::ACTIONS,
        'Organization Administrator' => self::ACTIONS,
        'Inventory Manager' => self::ACTIONS,
        'Sales Manager' => ['view'],
        'Finance Manager' => ['view', 'export'],
        'Accountant' => ['view'],
        'Read-only Auditor' => ['view'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::ACTIONS as $action) {
            DB::table('permissions')->insertOrIgnore(['name' => "suppliers.$action", 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', 'suppliers.%')->pluck('id', 'name');

        $rows = [];
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', array_keys(self::GRANTS))->get(['id', 'name']) as $role) {
            foreach (self::GRANTS[$role->name] as $action) {
                $rows[] = ['permission_id' => $ids["suppliers.$action"], 'role_id' => $role->id];
            }
        }
        DB::table('role_has_permissions')->insertOrIgnore($rows);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', 'suppliers.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
