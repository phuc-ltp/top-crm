<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 5.5 — quản lý tài sản: nhóm tài sản, tài sản (mã, nguyên giá, khấu hao đường thẳng, người giữ, mã QR),
 * nhật ký luân chuyển bất biến (cấp phát, thu hồi, mượn, trả, bảo trì, kiểm kê, thanh lý), đợt kiểm kê. Chỉ thêm bảng mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('depreciation_months')->default(36);   // mặc định cho tài sản mới trong nhóm
            $table->string('description', 500)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->string('model', 150)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 255)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('depreciation_months')->default(0);    // 0 = không khấu hao
            $table->date('warranty_until')->nullable();
            $table->string('status', 20)->default('available');                 // available | in_use | borrowed | maintenance | broken | lost | disposed
            $table->foreignId('holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_back_at')->nullable();                            // hạn trả khi mượn
            $table->string('qr_token', 40)->unique();                           // khóa ngẫu nhiên trong mã QR (không lộ id)
            $table->text('description')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->decimal('disposal_value', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
            $table->index('holder_id');
        });

        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);                                          // created | assigned | recalled | borrowed | returned | maintenance | repaired | checked | disposed | status
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();          // người thực hiện
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();   // người nhận / trả
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->decimal('cost', 15, 2)->nullable();                          // chi phí bảo trì / giá trị thanh lý
            $table->string('condition', 30)->nullable();                         // tình trạng khi bàn giao / kiểm kê
            $table->string('note', 1000)->nullable();
            $table->foreignId('asset_audit_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['asset_id', 'occurred_at']);
        });

        Schema::create('asset_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('open');                      // open | closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('asset_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('result', 20);                                        // found | damaged | missing
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['asset_audit_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_items');
        Schema::dropIfExists('asset_audits');
        Schema::dropIfExists('asset_events');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
