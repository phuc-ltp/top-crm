<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu tham gia (tự tạo tài khoản ở trang đăng nhập): người mới xác thực email bằng mã 6 số rồi CHỜ quản trị duyệt.
 * Mật khẩu lưu dạng băm (bcrypt) — chuyển thẳng sang tài khoản khi được duyệt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending');   // pending | approved | rejected
            $table->string('role', 100)->nullable();             // vai trò được cấp khi duyệt
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_requests');
    }
};
