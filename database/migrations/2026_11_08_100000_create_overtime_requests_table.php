<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 5.3 — đơn đề nghị tăng ca: ngày, giờ bắt đầu / kết thúc (trong khung tăng ca), số giờ, mức độ công việc, lý do,
 * trạng thái duyệt, người duyệt, đơn giá chốt lúc duyệt. Chỉ thêm bảng mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 5, 2);
            $table->string('level', 10);                         // high | medium | low
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');    // pending | approved | rejected | cancelled
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->decimal('rate', 12, 2)->nullable();           // đơn giá / giờ chốt lúc duyệt
            $table->timestamps();
            $table->index(['organization_id', 'work_date', 'status']);
            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
