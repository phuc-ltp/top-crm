<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nghỉ phép & ngày lễ: ngày lễ theo tổ chức, đơn nghỉ phép (cả ngày / nửa ngày, có duyệt), quỹ phép năm riêng từng người,
 * và cột giờ / tiền nghỉ có lương (phép + lễ) trên phiếu lương. Chỉ thêm bảng / cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name', 150);
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                  // annual | personal | sick | unpaid
            $table->date('start_date');
            $table->date('end_date');
            $table->string('part', 10)->default('full');                 // full | am | pm (am / pm chỉ khi nghỉ 1 ngày)
            $table->decimal('days', 5, 1);                               // số ngày làm việc nghỉ (đã trừ cuối tuần / ngày lễ)
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');            // pending | approved | rejected | cancelled
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'start_date', 'status']);
            $table->index(['user_id', 'start_date']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->decimal('annual_leave_days', 4, 1)->nullable()->after('dependents');   // trống = theo cài đặt tổ chức
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->unsignedInteger('paid_leave_minutes')->default(0)->after('late_minutes');
            $table->decimal('paid_leave_pay', 15, 2)->default(0)->after('base_pay');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['paid_leave_minutes', 'paid_leave_pay']);
        });
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('annual_leave_days');
        });
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('holidays');
    }
};
