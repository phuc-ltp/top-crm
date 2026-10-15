<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hệ thống đã có Super Admin thì ghi cờ "đã khởi tạo" để trang /setup đóng vĩnh viễn.
 * Chỉ THÊM một dòng vào settings, không sửa/xóa dữ liệu nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasAdmin = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('r.name', 'Super Admin')   // vai trò chỉ gán cho người dùng (model_type theo morph map)
            ->exists();

        if ($hasAdmin && ! DB::table('settings')->where('key', 'app_initialized_at')->exists()) {
            DB::table('settings')->insert([
                'key' => 'app_initialized_at', 'value' => now()->toIso8601String(), 'group' => 'system',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Cố ý không xóa cờ: xóa sẽ làm trang /setup mở lại — rủi ro chiếm quyền quản trị.
    }
};
