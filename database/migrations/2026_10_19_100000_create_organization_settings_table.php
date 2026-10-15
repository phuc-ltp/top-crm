<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cài đặt riêng của từng tổ chức (tên/logo/địa chỉ công ty, tiền tệ, định dạng ngày, thuế, thông báo...).
 * Sao chép giá trị đang dùng chung sang MỌI tổ chức hiện có → giao diện không thay đổi sau khi chạy.
 * Bảng `settings` giữ nguyên (cài đặt toàn hệ thống + dùng khi hoàn tác).
 */
return new class extends Migration
{
    /** Phải khớp App\Models\Setting::ORGANIZATION_KEYS tại thời điểm viết migration. */
    private const KEYS = [
        'company_name', 'company_email', 'company_phone', 'company_address', 'company_logo', 'brand_color',
        'timezone', 'currency', 'date_format', 'default_tax_rate',
        'notify_lead_assigned', 'notify_task_due', 'notify_deal_stage', 'notify_ticket_new',
    ];

    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        $global = DB::table('settings')->whereIn('key', self::KEYS)->pluck('value', 'key');
        $now = now();
        foreach (DB::table('organizations')->get(['id', 'name']) as $org) {
            $values = $global->all();
            // Tổ chức khác tổ chức mặc định: tên công ty = tên tổ chức (không mượn thông tin của tổ chức mặc định)
            if ((int) $org->id !== 1) {
                $values = ['company_name' => $org->name] + array_intersect_key($values, array_flip(['currency', 'date_format', 'timezone', 'default_tax_rate']));
            }
            $rows = [];
            foreach ($values as $key => $value) {
                $rows[] = ['organization_id' => $org->id, 'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('organization_settings')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
