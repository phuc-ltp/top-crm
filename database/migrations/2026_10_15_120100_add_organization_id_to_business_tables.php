<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm organization_id vào các bảng nghiệp vụ gốc, gán toàn bộ dữ liệu hiện có cho tổ chức mặc định (id = 1),
 * và chuyển các khóa UNIQUE thành "duy nhất trong từng tổ chức".
 * Bảng con (dòng chứng từ, bình luận, lịch sử...) thuộc tổ chức thông qua bảng cha nên không cần cột này.
 */
return new class extends Migration
{
    /** Bảng => có cho phép NULL (bản ghi cấp nền tảng, không thuộc tổ chức nào) */
    private const TABLES = [
        'departments' => false, 'lead_sources' => false, 'tags' => false, 'pipeline_stages' => false,
        'product_categories' => false, 'products' => false,
        'customers' => false, 'contacts' => false, 'leads' => false, 'campaigns' => false,
        'opportunities' => false, 'quotations' => false, 'sales_orders' => false, 'invoices' => false,
        'payments' => false, 'tasks' => false, 'support_tickets' => false, 'interactions' => false,
        'attachments' => false, 'sales_targets' => false,
        'activity_logs' => true,   // đăng nhập / thao tác hệ thống có thể không gắn tổ chức
    ];

    /** Khóa UNIQUE cũ => cột (sẽ thành UNIQUE theo tổ chức) */
    private const UNIQUES = [
        'customers' => ['customers_code_unique', ['code']],
        'departments' => ['departments_code_unique', ['code']],
        'invoices' => ['invoices_number_unique', ['number']],
        'leads' => ['leads_code_unique', ['code']],
        'lead_sources' => ['lead_sources_name_unique', ['name']],
        'opportunities' => ['opportunities_code_unique', ['code']],
        'pipeline_stages' => ['pipeline_stages_slug_unique', ['slug']],
        'products' => ['products_sku_unique', ['sku']],
        'product_categories' => ['product_categories_name_unique', ['name']],
        'quotations' => ['quotations_number_unique', ['number']],
        'sales_orders' => ['sales_orders_number_unique', ['number']],
        'sales_targets' => ['sales_targets_user_id_year_month_unique', ['user_id', 'year', 'month']],
        'support_tickets' => ['support_tickets_code_unique', ['code']],
        'tags' => ['tags_name_unique', ['name']],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $nullable) {
            if (Schema::hasColumn($table, 'organization_id')) {
                throw new RuntimeException("Bảng $table đã có cột organization_id — dừng để tránh ghi đè. Hãy kiểm tra trạng thái CSDL.");
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('organization_id')->nullable()->after('id');
            });
            DB::table($table)->update(['organization_id' => 1]);
            Schema::table($table, function (Blueprint $t) use ($nullable, $table) {
                if (! $nullable) {
                    $t->unsignedBigInteger('organization_id')->nullable(false)->change();
                }
                $t->index('organization_id', $table.'_organization_id_index');
                $t->foreign('organization_id', $table.'_organization_id_foreign')
                    ->references('id')->on('organizations')->restrictOnDelete();
            });
        }

        foreach (self::UNIQUES as $table => [$old, $columns]) {
            Schema::table($table, function (Blueprint $t) use ($old, $columns, $table) {
                // Thêm khóa mới TRƯỚC khi bỏ khóa cũ (khóa cũ có thể đang là index của một khóa ngoại)
                $t->unique(array_merge(['organization_id'], $columns), $table.'_org_'.implode('_', $columns).'_unique');
                if ($columns[0] === 'user_id') {
                    $t->index('user_id', $table.'_user_id_index');
                }
                $t->dropUnique($old);
            });
        }
    }

    public function down(): void
    {
        // Lưu ý: chỉ hoàn tác được khi các mã chưa trùng nhau giữa các tổ chức.
        foreach (self::UNIQUES as $table => [$old, $columns]) {
            Schema::table($table, function (Blueprint $t) use ($old, $columns, $table) {
                $t->unique($columns, $old);
                $t->dropUnique($table.'_org_'.implode('_', $columns).'_unique');
                if ($columns[0] === 'user_id') {
                    $t->dropIndex($table.'_user_id_index');   // index phụ do up() tạo; khóa cũ lại làm index cho khóa ngoại
                }
            });
        }
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign($table.'_organization_id_foreign');
                $t->dropIndex($table.'_organization_id_index');
                $t->dropColumn('organization_id');
            });
        }
    }
};
