<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link mời đăng ký (token chỉ lưu bản băm SHA-256; vai trò, hạn dùng, số lượt, thu hồi) và
 * mã xác thực qua email khi đăng nhập (users.email_otp_enabled). Chỉ thêm bảng / cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('role', 100);
            $table->string('note', 255)->nullable();
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->unsignedSmallInteger('uses')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_otp_enabled')->default(false)->after('two_factor_last_step');
            $table->foreignId('invited_via_id')->nullable()->after('email_otp_enabled')->constrained('invitations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_via_id');
            $table->dropColumn('email_otp_enabled');
        });
        Schema::dropIfExists('invitations');
    }
};
