<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phòng ban theo TƯ CÁCH THÀNH VIÊN: một người thuộc nhiều tổ chức có phòng ban riêng ở mỗi tổ chức.
 * Chuyển users.department_id sang organization_user.department_id (chỉ khi phòng ban thuộc đúng tổ chức đó).
 * Cột users.department_id được giữ lại (không còn dùng) để hoàn tác an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        DB::statement('UPDATE organization_user ou
            JOIN users u ON u.id = ou.user_id
            JOIN departments d ON d.id = u.department_id AND d.organization_id = ou.organization_id
            SET ou.department_id = u.department_id');
    }

    public function down(): void
    {
        // Ghi ngược phòng ban của tổ chức đang chọn (hoặc tổ chức mặc định) về users.department_id
        DB::statement('UPDATE users u
            JOIN organization_user ou ON ou.user_id = u.id AND ou.organization_id = COALESCE(u.current_organization_id, 1)
            SET u.department_id = ou.department_id
            WHERE ou.department_id IS NOT NULL');

        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
