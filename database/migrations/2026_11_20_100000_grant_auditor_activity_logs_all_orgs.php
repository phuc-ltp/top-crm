<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Đồng bộ giữa các tổ chức: vai trò "Read-only Auditor" theo ma trận mặc định có quyền activity_logs.view
 * nhưng tổ chức 1 (TOP CRM) đang thiếu. Chỉ THÊM quyền còn thiếu, không bỏ quyền nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->where('name', 'activity_logs.view')->where('guard_name', 'web')->value('id');
        if (! $permId) {
            return;
        }
        $roleIds = DB::table('roles')->where('name', 'Read-only Auditor')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permId, 'role_id' => $roleId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Không hoàn tác: quyền này đúng với ma trận mặc định của mọi tổ chức.
    }
};
