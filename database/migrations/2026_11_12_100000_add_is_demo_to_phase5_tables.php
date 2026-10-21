<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cờ dữ liệu demo cho các bảng Phase 5 không gắn với tài khoản (tài sản, đợt kiểm kê, giấy phép, kỳ lương) —
 * để crm:purge-demo xóa sạch. Dữ liệu gắn với tài khoản demo (hồ sơ, chấm công, tăng ca, phiếu lương) đã tự xóa theo khóa ngoại.
 * Chỉ thêm cột (mặc định false — dữ liệu hiện có không đổi).
 */
return new class extends Migration
{
    private const TABLES = ['assets', 'asset_audits', 'software_licenses', 'payroll_periods'];

    public function up(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->boolean('is_demo')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->dropIndex($t.'_is_demo_index');
                $table->dropColumn('is_demo');
            });
        }
    }
};
