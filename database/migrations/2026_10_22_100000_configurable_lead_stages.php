<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leads (bước 2.4):
 * - lead_stages: giai đoạn lead tự cấu hình theo tổ chức. Khởi tạo đúng 6 mã đang dùng (new, contacted, qualified,
 *   proposal, converted, lost) cho mọi tổ chức → lead cũ giữ nguyên trạng thái. "converted"/"lost" là giai đoạn hệ thống.
 * - lost_reasons: lý do mất (dùng cho lead, sau này cho cơ hội).
 * - leads.status: ENUM → chuỗi (để thêm giai đoạn mới); thêm qualified_at, lost_reason_id, lost_note,
 *   đồng ý nhận marketing (từ form website), chuyển sang khách hàng khi chuyển đổi.
 */
return new class extends Migration
{
    private const STAGES = [
        ['new', 'Mới', 'info', false, false],
        ['contacted', 'Đã liên hệ', 'primary', false, false],
        ['qualified', 'Đủ điều kiện', 'warning', false, true],
        ['proposal', 'Đàm phán', 'orange', false, false],
        ['converted', 'Đã chuyển đổi', 'success', true, false],
        ['lost', 'Thất bại', 'danger', true, false],
    ];

    private const LOST_REASONS = ['Giá cao so với ngân sách', 'Chọn đối thủ cạnh tranh', 'Không còn nhu cầu', 'Không liên lạc được', 'Thông tin không chính xác', 'Khác'];

    public function up(): void
    {
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('name', 100);
            $table->string('color', 20)->default('secondary');   // màu badge: info, primary, warning, orange, success, danger, purple, secondary
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_system')->default(false);         // converted / lost: không xóa được
            $table->boolean('is_qualified')->default(false);      // giai đoạn "đủ điều kiện" (ghi qualified_at, phân tích)
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('applies_to', 20)->default('both');   // lead | opportunity | both
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        DB::statement("ALTER TABLE leads MODIFY status VARCHAR(50) NOT NULL DEFAULT 'new'");
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('qualified_at')->nullable()->after('status');
            $table->foreignId('lost_reason_id')->nullable()->after('qualified_at')->constrained('lost_reasons')->nullOnDelete();
            $table->string('lost_note', 500)->nullable()->after('lost_reason_id');
            $table->boolean('marketing_consent')->default(false)->after('notes');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            $table->string('marketing_consent_source', 100)->nullable()->after('marketing_consent_at');
            $table->index(['organization_id', 'status']);
        });

        $now = now();
        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            foreach (self::STAGES as $i => [$key, $name, $color, $system, $qualified]) {
                DB::table('lead_stages')->insertOrIgnore(['organization_id' => $orgId, 'key' => $key, 'name' => $name, 'color' => $color,
                    'position' => $i + 1, 'is_system' => $system, 'is_qualified' => $qualified, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach (self::LOST_REASONS as $i => $name) {
                DB::table('lost_reasons')->insertOrIgnore(['organization_id' => $orgId, 'name' => $name, 'applies_to' => 'both',
                    'position' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        // Lead cũ: không tự đoán qualified_at (để trống) — chỉ ghi khi lead chuyển vào giai đoạn đủ điều kiện từ nay

    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropForeign(['lost_reason_id']);
            $table->dropColumn(['qualified_at', 'lost_reason_id', 'lost_note', 'marketing_consent', 'marketing_consent_at', 'marketing_consent_source']);
        });
        // Giai đoạn tự thêm không có trong ENUM cũ → đưa về "new" trước khi thu hẹp kiểu cột
        DB::table('leads')->whereNotIn('status', ['new', 'contacted', 'qualified', 'proposal', 'converted', 'lost'])->update(['status' => 'new']);
        DB::statement("ALTER TABLE leads MODIFY status ENUM('new','contacted','qualified','proposal','converted','lost') NOT NULL DEFAULT 'new'");
        Schema::dropIfExists('lost_reasons');
        Schema::dropIfExists('lead_stages');
    }
};
