<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "inventory.*": view (xem tồn kho, sổ kho, phiếu kho), create (lập phiếu nhập / xuất / chuyển),
 * adjust (phiếu điều chỉnh kiểm kê), manage (quản lý kho, xem giá vốn & giá trị tồn kho).
 * Gán theo ma trận mặc định của PermissionCatalog tại thời điểm này, ở MỌI tổ chức. Không đổi quyền nào khác.
 */
return new class extends Migration
{
    private const ACTIONS = ['view', 'create', 'adjust', 'manage'];

    private const GRANTS = [
        'Organization Owner' => self::ACTIONS,
        'Organization Administrator' => self::ACTIONS,
        'Inventory Manager' => self::ACTIONS,
        'Sales Manager' => ['view'],
        'Sales Employee' => ['view'],
        'Finance Manager' => ['view'],
        'Accountant' => ['view'],
        'Read-only Auditor' => ['view'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::ACTIONS as $action) {
            DB::table('permissions')->insertOrIgnore(['name' => "inventory.$action", 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', 'inventory.%')->pluck('id', 'name');

        $rows = [];
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', array_keys(self::GRANTS))->get(['id', 'name']) as $role) {
            foreach (self::GRANTS[$role->name] as $action) {
                $rows[] = ['permission_id' => $ids["inventory.$action"], 'role_id' => $role->id];
            }
        }
        DB::table('role_has_permissions')->insertOrIgnore($rows);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('guard_name', 'web')->where('name', 'like', 'inventory.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
