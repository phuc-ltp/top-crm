<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 5.4 — bảng lương: kỳ lương theo tháng (nháp → chờ duyệt → đã duyệt → đã trả) và phiếu lương từng người
 * (ảnh chụp số liệu tại thời điểm tính: công, đơn giá, tăng ca, phụ cấp, bảo hiểm, thuế TNCN, thực nhận).
 * Thêm employee_profiles.dependents (số người phụ thuộc để giảm trừ gia cảnh). Chỉ thêm bảng / cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period');                                  // ngày đầu tháng
            $table->string('status', 20)->default('draft');          // draft | submitted | approved | paid
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'period']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->unsignedSmallInteger('work_days')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->decimal('base_pay', 15, 2)->default(0);
            $table->decimal('ot_hours', 7, 2)->default(0);
            $table->decimal('ot_pay', 15, 2)->default(0);
            $table->decimal('ot_exempt', 15, 2)->default(0);         // phần chênh tăng ca được miễn thuế TNCN
            $table->decimal('allowance', 15, 2)->default(0);          // nhập tay
            $table->decimal('bonus', 15, 2)->default(0);              // nhập tay
            $table->decimal('other_deduction', 15, 2)->default(0);    // nhập tay (tạm ứng, phạt...)
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('insurance_base', 15, 2)->default(0);
            $table->decimal('social_insurance', 15, 2)->default(0);
            $table->decimal('health_insurance', 15, 2)->default(0);
            $table->decimal('unemployment_insurance', 15, 2)->default(0);
            $table->unsignedTinyInteger('dependents')->default(0);
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('personal_income_tax', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->json('meta')->nullable();                         // tham số đã dùng (tỷ lệ BH, mức giảm trừ, trần BH)
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'user_id']);
            $table->index(['organization_id', 'user_id']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('dependents')->default(0)->after('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('dependents');
        });
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
    }
};
