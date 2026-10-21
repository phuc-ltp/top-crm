<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 5.6 — bản quyền phần mềm: giấy phép (khóa mã hóa, số chỗ, hạn dùng, chi phí, người phụ trách, mốc nhắc gần nhất)
 * và lịch sử gán cho người / thiết bị. Thêm assets.overdue_reminded_on để nhắc tài sản mượn quá hạn mỗi ngày một lần.
 * Chỉ thêm bảng / cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vendor', 150)->nullable();
            $table->string('edition', 150)->nullable();
            $table->string('license_type', 20)->default('subscription');      // subscription | perpetual | volume | oem | free
            $table->text('license_key')->nullable();                           // mã hóa (cast encrypted)
            $table->unsignedInteger('seats')->default(1);
            $table->date('purchase_date')->nullable();
            $table->date('expires_at')->nullable();                            // trống = vĩnh viễn
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('billing_cycle', 20)->default('yearly');            // monthly | yearly | one_time
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();   // người phụ trách gia hạn
            $table->unsignedSmallInteger('reminded_stage')->nullable();        // mốc nhắc đã gửi (30 / 7 / 0 ngày) cho hạn hiện tại
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'expires_at']);
        });

        Schema::create('license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('software_license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();                      // trống = đang dùng chỗ
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['software_license_id', 'released_at']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->date('overdue_reminded_on')->nullable()->after('due_back_at');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('overdue_reminded_on');
        });
        Schema::dropIfExists('license_assignments');
        Schema::dropIfExists('software_licenses');
    }
};
