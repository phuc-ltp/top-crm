<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảo mật tài khoản: xác thực 2 lớp (TOTP) và lịch sử đăng nhập. Chỉ thêm cột / bảng mới.
 * - two_factor_secret, two_factor_recovery_codes: lưu MÃ HÓA bằng APP_KEY (mã khôi phục chỉ lưu dạng băm).
 * - two_factor_last_step: bước thời gian của mã vừa dùng → chặn dùng lại cùng một mã.
 * - login_events: thuộc tài khoản (không thuộc tổ chức), tự xóa sau thời hạn lưu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event', 30);            // login | failed | two_factor_failed | blocked | locked_out
            $table->string('method', 20)->nullable(); // password | google | facebook | totp | recovery_code
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('device_hash', 64)->nullable();   // băm SHA-256 của cookie thiết bị, không lưu cookie gốc
            $table->boolean('is_new_device')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'device_hash']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
