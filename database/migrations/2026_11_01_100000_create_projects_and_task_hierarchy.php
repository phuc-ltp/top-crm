<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 4.1 — dự án, thành viên dự án; công việc có thêm dự án, việc con, ngày bắt đầu, giờ ước tính;
 * phụ thuộc giữa các công việc. Chỉ thêm bảng / cột mới (nullable), công việc cũ giữ nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('planning');
            $table->string('priority', 20)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('project_id')->constrained('tasks')->nullOnDelete();
            $table->date('start_date')->nullable()->after('status');
            $table->decimal('estimated_hours', 6, 2)->nullable()->after('due_date');
            $table->index(['project_id', 'status']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();          // việc bị chặn
            $table->foreignId('depends_on_id')->constrained('tasks')->cascadeOnDelete(); // việc phải xong trước
            $table->timestamps();
            $table->unique(['task_id', 'depends_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
        // Gỡ khóa ngoại trước: chỉ mục (project_id, status) đang được khóa ngoại project_id dùng
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['project_id']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropColumn(['project_id', 'parent_id', 'start_date', 'estimated_hours']);
        });
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
