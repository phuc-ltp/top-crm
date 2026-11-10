<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giao dịch ngân hàng nhận qua webhook (SePay). Mỗi giao dịch chỉ lưu một lần: unique (tổ chức, nhà cung cấp, id giao dịch)
 * — SePay giữ nguyên id qua mọi lần gửi lại. Khớp được hóa đơn thì ghi nhận thanh toán (payment_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->unsignedBigInteger('provider_id');
            $table->string('gateway', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('transfer_type', 10);
            $table->decimal('amount', 15, 2);
            $table->text('content')->nullable();
            $table->string('code', 100)->nullable();
            $table->string('reference_code', 100)->nullable();
            $table->timestamp('transaction_at')->nullable();
            // matched: đã ghi nhận vào hóa đơn | unmatched: cần xử lý tay | ignored: tiền ra / không áp dụng
            $table->string('status', 20)->index();
            $table->string('note', 255)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'provider', 'provider_id']);
            $table->index(['organization_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
