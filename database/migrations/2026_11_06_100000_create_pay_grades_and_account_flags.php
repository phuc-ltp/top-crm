<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 5.1 — bậc lương theo giờ, bậc / đơn giá riêng trong hồ sơ nhân viên, cờ bắt buộc đổi mật khẩu cho tài khoản tạo nhanh.
 * Chỉ thêm bảng / cột mới; dữ liệu cũ giữ nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('hourly_rate', 12, 2);
            $table->json('roles')->nullable();          // vai trò dùng bậc này làm mặc định
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->foreignId('pay_grade_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('pay_grade_id');   // đơn giá riêng (ưu tiên hơn bậc)
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropForeign(['pay_grade_id']);
        });
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['pay_grade_id', 'hourly_rate']);
        });
        Schema::dropIfExists('pay_grades');
    }
};
