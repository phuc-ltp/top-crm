<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 4.2 — chi nhánh, nhóm, thành viên nhóm, hồ sơ nhân viên (1–1 với thành viên của tổ chức).
 * Thông tin nhạy cảm trong hồ sơ được mã hóa bằng APP_KEY (cột text). Chỉ thêm bảng mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 1000)->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code', 30)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('hire_date')->nullable();
            $table->string('employment_status', 20)->default('active');
            $table->string('work_phone', 30)->nullable();
            // Nhạy cảm — mã hóa (cast "encrypted"), chỉ người có employees.view_sensitive xem / sửa
            $table->text('date_of_birth')->nullable();
            $table->text('personal_phone')->nullable();
            $table->text('personal_email')->nullable();
            $table->text('home_address')->nullable();
            $table->text('id_number')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->text('hr_notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'employee_code']);
            $table->index(['organization_id', 'manager_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('branches');
    }
};
