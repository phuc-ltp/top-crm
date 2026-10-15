<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phân quyền 2 tầng:
 *  - Tầng nền tảng: users.is_platform_admin (thay cho vai trò "Super Admin" dùng chung).
 *  - Tầng tổ chức: vai trò thuộc từng tổ chức (spatie/laravel-permission chế độ teams, khóa = organization_id).
 * Vai trò cũ được ĐỔI TÊN (không xóa) và giữ nguyên quyền + người được gán; mọi thứ thuộc tổ chức mặc định (id = 1).
 */
return new class extends Migration
{
    /** Tên cũ => tên mới */
    private const RENAME = [
        'Super Admin' => 'Organization Owner',
        'Administrator' => 'Organization Administrator',
        'Sales Staff' => 'Sales Employee',
        'Marketing Staff' => 'Marketing Employee',
        'Customer Support' => 'Support Agent',
        'Viewer' => 'Read-only Auditor',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('users', 'is_platform_admin')) {
            throw new RuntimeException('Cấu trúc phân quyền đã được nâng cấp trước đó — dừng để tránh ghi đè.');
        }
        // Cài đặt mới: migration gốc của spatie đã tạo sẵn cột team khi teams = true → chỉ cần phần dữ liệu
        $upgradeStructure = ! Schema::hasColumn('roles', 'organization_id');

        // 1) Quản trị nền tảng: người đang giữ vai trò "Super Admin"
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false)->after('status');
        });
        $superAdminRoleId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        if ($superAdminRoleId) {
            $ids = DB::table('model_has_roles')->where('role_id', $superAdminRoleId)->pluck('model_id');
            DB::table('users')->whereIn('id', $ids)->update(['is_platform_admin' => true]);
        }

        if ($upgradeStructure) {
            $this->upgradeStructure();
        }

        // 4) Đổi tên vai trò cũ theo bộ 15 vai trò
        foreach (self::RENAME as $old => $new) {
            DB::table('roles')->where('name', $old)->update(['name' => $new]);
        }
    }

    /** Hệ thống đang chạy (teams = false trước đây): thêm cột tổ chức vào bảng phân quyền, gán dữ liệu cũ cho tổ chức 1. */
    private function upgradeStructure(): void
    {
        // 2) roles.organization_id + tên vai trò duy nhất trong từng tổ chức
        Schema::table('roles', function (Blueprint $t) {
            $t->unsignedBigInteger('organization_id')->nullable()->after('id');
            $t->index('organization_id', 'roles_team_foreign_key_index');
        });
        DB::table('roles')->update(['organization_id' => 1]);
        Schema::table('roles', function (Blueprint $t) {
            $t->foreign('organization_id', 'roles_organization_id_foreign')->references('id')->on('organizations')->restrictOnDelete();
            $t->unique(['organization_id', 'name', 'guard_name'], 'roles_team_name_guard_unique');
            $t->dropUnique('roles_name_guard_name_unique');
        });

        // 3) Gán vai trò / quyền trực tiếp theo tổ chức
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $table => $pivot) {
            Schema::table($table, function (Blueprint $t) use ($table, $pivot) {
                $t->unsignedBigInteger('organization_id')->nullable()->first();
                $t->index($pivot, $table.'_'.$pivot.'_index');   // giữ index cho khóa ngoại trước khi đổi khóa chính
            });
            DB::table($table)->update(['organization_id' => 1]);
            Schema::table($table, function (Blueprint $t) use ($table, $pivot) {
                $t->unsignedBigInteger('organization_id')->nullable(false)->change();
                $t->index('organization_id', $table.'_team_foreign_key_index');
                $t->dropPrimary();
                $t->primary(['organization_id', $pivot, 'model_id', 'model_type']);
            });
        }
    }

    public function down(): void
    {
        // Lưu ý: chỉ hoàn tác được khi chưa có tổ chức thứ hai dùng trùng tên vai trò.
        foreach (self::RENAME as $old => $new) {
            DB::table('roles')->where('name', $new)->update(['name' => $old]);
        }
        // Chỉ gỡ cấu trúc nếu chính migration này đã thêm (cài đặt mới thì cột team thuộc migration gốc của spatie)
        if (! Schema::hasIndex('roles', 'roles_team_name_guard_unique')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));

            return;
        }
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $table => $pivot) {
            Schema::table($table, function (Blueprint $t) use ($table, $pivot) {
                $t->dropPrimary();
                $t->primary([$pivot, 'model_id', 'model_type']);
                $t->dropIndex($table.'_team_foreign_key_index');
                $t->dropIndex($table.'_'.$pivot.'_index');
                $t->dropColumn('organization_id');
            });
        }
        Schema::table('roles', function (Blueprint $t) {
            $t->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            $t->dropUnique('roles_team_name_guard_unique');
            $t->dropForeign('roles_organization_id_foreign');
            $t->dropIndex('roles_team_foreign_key_index');
            $t->dropColumn('organization_id');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_platform_admin');
        });
    }
};
