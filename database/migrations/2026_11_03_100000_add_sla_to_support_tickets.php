<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 4.3 — danh mục ticket, SLA (hạn phản hồi đầu / hạn xử lý, tạm dừng khi chờ khách), leo thang,
 * điểm hài lòng, lịch sử đổi trạng thái. Chỉ thêm bảng / cột mới (nullable); ticket cũ giữ nguyên (SLA để trống).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('contact_id')->constrained('ticket_categories')->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable()->after('closed_at');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->timestamp('first_responded_at')->nullable()->after('resolution_due_at');
            $table->timestamp('paused_at')->nullable()->after('first_responded_at');
            $table->unsignedInteger('paused_minutes')->default(0)->after('paused_at');
            $table->unsignedTinyInteger('escalation_level')->default(0)->after('paused_minutes');   // 0 chưa, 1 cảnh báo sắp trễ, 2 đã leo thang
            $table->timestamp('escalated_at')->nullable()->after('escalation_level');
            $table->unsignedTinyInteger('satisfaction_rating')->nullable()->after('escalated_at');
            $table->string('satisfaction_comment', 1000)->nullable()->after('satisfaction_rating');
            $table->index(['organization_id', 'status', 'resolution_due_at']);
        });

        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_histories');
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'resolution_due_at']);
            $table->dropColumn(['category_id', 'first_response_due_at', 'resolution_due_at', 'first_responded_at', 'paused_at', 'paused_minutes',
                'escalation_level', 'escalated_at', 'satisfaction_rating', 'satisfaction_comment']);
        });
        Schema::dropIfExists('ticket_categories');
    }
};
