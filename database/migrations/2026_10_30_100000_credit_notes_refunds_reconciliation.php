<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 3.5 — hóa đơn & thanh toán: phiếu giảm trừ (credit note), hoàn tiền (khoản thanh toán âm loại "refund"),
 * đối soát thanh toán, mốc đã nhắc hạn thanh toán.
 * Chỉ thêm bảng / cột; thanh toán cũ = loại "payment", chưa đối soát; hóa đơn cũ chưa có giảm trừ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->decimal('amount', 15, 2);
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('type', 20)->default('payment')->after('invoice_id');
            $table->timestamp('reconciled_at')->nullable()->after('recorded_by');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('credited_amount', 15, 2)->default(0)->after('amount_paid');
            $table->timestamp('due_reminder_sent_at')->nullable()->after('credited_amount');
            $table->timestamp('overdue_notified_at')->nullable()->after('due_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['credited_amount', 'due_reminder_sent_at', 'overdue_notified_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn(['type', 'reconciled_at', 'reconciled_by']);
        });
        Schema::dropIfExists('credit_notes');
    }
};
