<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 5.2 — chấm công: văn phòng (tọa độ + bán kính), bản ghi công theo ngày (vào / ra, vị trí, ảnh selfie, giờ công),
 * thời điểm nhân viên đồng ý thu thập ảnh & vị trí. Chỉ thêm bảng / cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_m')->default(150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            foreach (['check_in', 'check_out'] as $p) {
                $table->timestamp($p.'_at')->nullable();
                $table->decimal($p.'_lat', 10, 7)->nullable();
                $table->decimal($p.'_lng', 10, 7)->nullable();
                $table->unsignedInteger($p.'_accuracy')->nullable();
                $table->unsignedInteger($p.'_distance')->nullable();
                $table->foreignId($p.'_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
                $table->boolean($p.'_within')->nullable();
                $table->string($p.'_selfie')->nullable();
                $table->string($p.'_ip', 45)->nullable();
            }
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0);
            $table->string('status', 20)->default('absent');   // present | late | incomplete | absent
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable();
            $table->string('adjusted_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'work_date']);
            $table->index(['organization_id', 'work_date']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->timestamp('attendance_consent_at')->nullable()->after('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('attendance_consent_at');
        });
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('work_locations');
    }
};
