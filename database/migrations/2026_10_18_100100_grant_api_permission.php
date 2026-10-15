<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quyền mới "api.use" (tạo khóa API cá nhân). Gán sẵn cho Chủ sở hữu và Quản trị viên tổ chức ở MỌI tổ chức;
 * các vai trò khác do chủ sở hữu tự cấp trong trang phân quyền. Không đổi quyền nào khác.
 */
return new class extends Migration
{
    private const PERMISSION = 'api.use';

    private const ROLES = ['Organization Owner', 'Organization Administrator'];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore(['name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        $rows = DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', 'web')->pluck('id')
            ->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->all();
        DB::table('role_has_permissions')->insertOrIgnore($rows);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
