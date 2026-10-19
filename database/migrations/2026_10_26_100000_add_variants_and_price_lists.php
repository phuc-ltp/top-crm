<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 3.1b — biến thể sản phẩm (sản phẩm con của một sản phẩm cha, 1 cấp) và bảng giá theo nhóm khách hàng.
 * Chỉ thêm cột / bảng mới; sản phẩm và khách hàng cũ không đổi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('products')->nullOnDelete();
            $table->string('variant_name', 100)->nullable()->after('name');
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();
            $table->unique(['price_list_id', 'product_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('price_list_id')->nullable()->after('lead_source_id')->constrained('price_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn('price_list_id');
        });
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'variant_name']);
        });
    }
};
