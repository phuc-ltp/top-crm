<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 3.1 — sản phẩm: loại (sản phẩm / dịch vụ), giá vốn, nhà cung cấp chính.
 * Chỉ thêm cột; sản phẩm cũ = "sản phẩm", chưa có giá vốn / nhà cung cấp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('product')->after('sku');
            $table->decimal('purchase_price', 15, 2)->nullable()->after('price');
            $table->foreignId('supplier_id')->nullable()->after('product_category_id')->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'purchase_price', 'type']);
        });
    }
};
