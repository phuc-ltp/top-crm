<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "employees.*" (hồ sơ / danh bạ nhân viên) và "teams.*" (nhóm, chi nhánh). Gán cho các vai trò ở MỌI tổ chức
 * theo đúng ma trận mặc định của PermissionCatalog tại thời điểm này (ghi cứng). Không đổi quyền nào khác.
 * Thông tin nhạy cảm (employees.view_sensitive), sửa hồ sơ và xuất chỉ dành cho chủ sở hữu / quản trị tổ chức.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'employees.view', 'employees.view_sensitive', 'employees.update', 'employees.export',
        'teams.view', 'teams.manage',
    ];

    private const GRANTS = [
        'Organization Owner' => self::PERMISSIONS,
        'Organization Administrator' => self::PERMISSIONS,
        'Sales Manager' => ['employees.view', 'teams.view'],
        'Sales Employee' => ['employees.view', 'teams.view'],
        'Marketing Manager' => ['employees.view', 'teams.view'],
        'Marketing Employee' => ['employees.view', 'teams.view'],
        'Customer Support Manager' => ['employees.view', 'teams.view'],
        'Support Agent' => ['employees.view', 'teams.view'],
        'Finance Manager' => ['employees.view', 'teams.view'],
        'Accountant' => ['employees.view', 'teams.view'],
        'Inventory Manager' => ['employees.view', 'teams.view'],
        'Project Manager' => ['employees.view', 'teams.view'],
        'Employee' => ['employees.view', 'teams.view'],
        'Read-only Auditor' => ['employees.view', 'teams.view'],
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
