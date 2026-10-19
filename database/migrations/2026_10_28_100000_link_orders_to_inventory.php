<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 3.3 — đơn hàng ↔ kho: kho xuất của đơn hàng, giữ hàng (reservations), liên kết phiếu kho với đơn hàng
 * (phiếu xuất giao hàng, phiếu nhập hàng trả lại).
 * Đơn hàng cũ để trống kho → không phát sinh kho (không trừ kho ngược lại cho đơn đã có).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('quotation_id')->constrained()->restrictOnDelete();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
            $table->unique(['sales_order_id', 'product_id']);
        });

        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });
        Schema::dropIfExists('stock_reservations');
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
