<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 3.4 — mẫu báo giá và duyệt báo giá có chiết khấu vượt ngưỡng của tổ chức.
 * Chỉ thêm bảng / cột; báo giá cũ không cần duyệt (approval_status = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('items');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('approval_status', 20)->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('approval_note', 500)->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at', 'approval_note']);
        });
        Schema::dropIfExists('quotation_templates');
    }
};
