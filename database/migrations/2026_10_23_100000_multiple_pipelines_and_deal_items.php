<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 2.5 — nhiều pipeline & cơ hội đầy đủ:
 * - pipelines: mỗi tổ chức có một pipeline mặc định chứa đúng các giai đoạn hiện có (cơ hội cũ không đổi giai đoạn).
 * - pipeline_stages.pipeline_id; mã giai đoạn (slug) duy nhất trong từng pipeline thay vì toàn tổ chức.
 * - opportunities.pipeline_id (lấy từ giai đoạn hiện tại), lost_reason_id (lý do có cấu trúc; chữ cũ trong lost_reason giữ làm ghi chú).
 * - opportunity_items: sản phẩm / dịch vụ trong cơ hội.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        $now = now();
        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            DB::table('pipelines')->insert(['organization_id' => $orgId, 'name' => 'Pipeline bán hàng', 'is_default' => true, 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->foreignId('pipeline_id')->nullable()->after('organization_id');
        });
        DB::statement('UPDATE pipeline_stages ps JOIN pipelines p ON p.organization_id = ps.organization_id AND p.is_default = 1 SET ps.pipeline_id = p.id');
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->foreignId('pipeline_id')->nullable(false)->change();
            $table->foreign('pipeline_id')->references('id')->on('pipelines')->cascadeOnDelete();
            $table->unique(['pipeline_id', 'slug'], 'pipeline_stages_pipeline_slug_unique');
            $table->dropUnique('pipeline_stages_org_slug_unique');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('pipeline_id')->nullable()->after('lead_id');
            $table->foreignId('lost_reason_id')->nullable()->after('lost_reason')->constrained('lost_reasons')->nullOnDelete();
        });
        DB::statement('UPDATE opportunities o JOIN pipeline_stages ps ON ps.id = o.pipeline_stage_id SET o.pipeline_id = ps.pipeline_id');
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('pipeline_id')->nullable(false)->change();
            $table->foreign('pipeline_id')->references('id')->on('pipelines')->restrictOnDelete();
            $table->index(['organization_id', 'pipeline_id']);
        });

        Schema::create('opportunity_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Tổ chức ĐÃ có dữ liệu mặc định (có phòng ban hoặc nguồn khách) → đánh dấu để lần khởi động sau không tạo lại
        // thứ người dùng đã xóa. Bản cài mới (tổ chức chưa có dữ liệu) không bị đánh dấu → vẫn được tạo dữ liệu mặc định.
        $provisioned = DB::table('organizations')->whereExists(fn ($q) => $q->from('departments')->whereColumn('departments.organization_id', 'organizations.id'))
            ->orWhereExists(fn ($q) => $q->from('lead_sources')->whereColumn('lead_sources.organization_id', 'organizations.id'))->pluck('id');
        foreach ($provisioned as $orgId) {
            DB::table('organization_settings')->insertOrIgnore(['organization_id' => $orgId, 'key' => 'provisioned_at', 'value' => $now->toDateTimeString(), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('organization_settings')->where('key', 'provisioned_at')->delete();
        Schema::dropIfExists('opportunity_items');

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'pipeline_id']);
            $table->dropForeign(['pipeline_id']);
            $table->dropForeign(['lost_reason_id']);
            $table->dropColumn(['pipeline_id', 'lost_reason_id']);
        });

        // Chỉ giữ giai đoạn của pipeline mặc định (pipeline thêm sau không tồn tại ở cấu trúc cũ);
        // cơ hội ở pipeline khác được đưa về giai đoạn đầu của pipeline mặc định trước khi xóa
        DB::statement('UPDATE opportunities o
            JOIN pipeline_stages ps ON ps.id = o.pipeline_stage_id
            JOIN pipelines p ON p.id = ps.pipeline_id AND p.is_default = 0
            JOIN pipelines d ON d.organization_id = p.organization_id AND d.is_default = 1
            SET o.pipeline_stage_id = (SELECT s.id FROM pipeline_stages s WHERE s.pipeline_id = d.id ORDER BY s.position LIMIT 1)');
        DB::statement('DELETE ps FROM pipeline_stages ps JOIN pipelines p ON p.id = ps.pipeline_id AND p.is_default = 0');

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->unique(['organization_id', 'slug'], 'pipeline_stages_org_slug_unique');
            $table->dropForeign(['pipeline_id']);
            $table->dropUnique('pipeline_stages_pipeline_slug_unique');
            $table->dropColumn('pipeline_id');
        });
        Schema::dropIfExists('pipelines');
    }
};
