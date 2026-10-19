<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 3.2 — kho hàng: kho, tồn kho theo kho × sản phẩm, phiếu kho (nhập / xuất / điều chỉnh / chuyển),
 * sổ kho bất biến (stock_movements). Sản phẩm thêm mức tồn tối thiểu và giá vốn bình quân.
 * Chỉ thêm bảng / cột mới; mỗi tổ chức hiện có được tạo sẵn "Kho chính" (chưa có tồn kho nào).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('address', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('reorder_level', 15, 3)->nullable()->after('purchase_price');
            $table->decimal('average_cost', 15, 2)->nullable()->after('reorder_level');
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('type', 20);
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'type', 'document_date']);
        });

        Schema::create('stock_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 3);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['organization_id', 'product_id', 'warehouse_id']);
            $table->index(['organization_id', 'created_at']);
        });

        // Kho chính cho mỗi tổ chức hiện có (cấu hình mặc định, không phải dữ liệu kinh doanh)
        $now = now();
        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            if (! DB::table('warehouses')->where('organization_id', $orgId)->exists()) {
                DB::table('warehouses')->insert(['organization_id' => $orgId, 'code' => 'KHO-CHINH', 'name' => 'Kho chính', 'is_default' => true,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_document_items');
        Schema::dropIfExists('stock_documents');
        Schema::dropIfExists('stock_levels');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reorder_level', 'average_cost']);
        });
        Schema::dropIfExists('warehouses');
    }
};
