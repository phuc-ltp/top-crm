<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ khách hàng đầy đủ: quốc gia, phân khúc, giai đoạn vòng đời, kênh liên lạc ưa thích,
 * đồng ý nhận marketing (thời điểm + nguồn), trường tùy biến (JSON), gộp khách hàng (merged_into_id).
 * Chỉ thêm cột; khách hàng cũ để trống (không tự đoán), chưa đồng ý marketing cho tới khi có xác nhận.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('address');
            $table->string('segment', 20)->nullable()->after('status');
            $table->string('lifecycle_stage', 20)->nullable()->after('segment');
            $table->timestamp('lifecycle_changed_at')->nullable()->after('lifecycle_stage');
            $table->string('preferred_channel', 20)->nullable()->after('lifecycle_changed_at');
            $table->boolean('marketing_consent')->default(false)->after('preferred_channel');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            $table->string('marketing_consent_source', 100)->nullable()->after('marketing_consent_at');
            $table->json('custom_fields')->nullable()->after('notes');
            $table->foreignId('merged_into_id')->nullable()->after('custom_fields')->constrained('customers')->nullOnDelete();

            $table->index(['organization_id', 'segment']);
            $table->index(['organization_id', 'lifecycle_stage']);
            $table->index(['organization_id', 'country']);
        });

        // Định nghĩa trường tùy biến của từng tổ chức (hiện dùng cho khách hàng; cột entity để mở rộng sau)
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 30)->default('customer');
            $table->string('key', 50);
            $table->string('label', 100);
            $table->string('type', 20);                 // text | textarea | number | date | select | checkbox
            $table->json('options')->nullable();        // lựa chọn cho kiểu select
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'entity', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'segment']);
            $table->dropIndex(['organization_id', 'lifecycle_stage']);
            $table->dropIndex(['organization_id', 'country']);
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn(['country', 'segment', 'lifecycle_stage', 'lifecycle_changed_at', 'preferred_channel',
                'marketing_consent', 'marketing_consent_at', 'marketing_consent_source', 'custom_fields', 'merged_into_id']);
        });
    }
};
