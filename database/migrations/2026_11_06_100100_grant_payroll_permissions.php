<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "payroll.*": view_all (xem lương / bảng lương của mọi người), manage (bậc lương, đơn giá, lập & duyệt bảng lương).
 * Mỗi nhân viên luôn xem được lương của chính mình (không cần quyền). Ghi cứng theo ma trận mặc định tại thời điểm này.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['payroll.view_all', 'payroll.manage'];

    private const GRANTS = [
        'Organization Owner' => self::PERMISSIONS,
        'Organization Administrator' => self::PERMISSIONS,
        'Finance Manager' => self::PERMISSIONS,
        'Accountant' => ['payroll.view_all'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id', 'name');
        $rows = [];
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', array_keys(self::GRANTS))->get(['id', 'name']) as $role) {
            foreach (self::GRANTS[$role->name] as $name) {
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
