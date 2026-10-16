<?php

namespace App\Support\Tenancy;

use App\Models\Department;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\PipelineStage;
use App\Models\ProductCategory;
use App\Models\Tag;

/**
 * Khởi tạo dữ liệu mặc định cho một tổ chức: bộ vai trò, phòng ban, nguồn khách hàng, giai đoạn bán hàng,
 * danh mục sản phẩm, thẻ. An toàn khi chạy lại (không tạo trùng, không ghi đè thứ tổ chức đã tự sửa).
 */
class OrganizationProvisioner
{
    public const DEPARTMENTS = [
        ['BGD', 'Ban Giám đốc'], ['KD', 'Phòng Kinh doanh'], ['MKT', 'Phòng Marketing'],
        ['CSKH', 'Phòng Chăm sóc khách hàng'], ['KT', 'Phòng Kế toán'],
    ];

    public const LEAD_SOURCES = ['Website', 'Facebook', 'Zalo', 'Google Ads', 'Giới thiệu', 'Sự kiện', 'Gọi điện', 'Email', 'Khác'];

    /** slug, tên, xác suất, màu, thắng, thua */
    public const STAGES = [
        ['new', 'Cơ hội mới', 10, '#6B7280', false, false],
        ['qualification', 'Đánh giá nhu cầu', 25, '#3B82F6', false, false],
        ['proposal', 'Gửi đề xuất', 50, '#F59E0B', false, false],
        ['negotiation', 'Đàm phán', 75, '#FF6B00', false, false],
        ['won', 'Thắng', 100, '#16A34A', true, false],
        ['lost', 'Thất bại', 0, '#DC2626', false, true],
    ];

    public const PRODUCT_CATEGORIES = ['Phần mềm', 'Dịch vụ', 'Phần cứng', 'Đào tạo', 'Gói hỗ trợ'];

    public const TAGS = [['VIP', '#FF6B00'], ['Doanh nghiệp lớn', '#2563EB'], ['Khách quen', '#16A34A'], ['Cần chăm sóc', '#F59E0B'], ['Đối tác', '#7C3AED']];

    public function provision(Organization|int $organization): void
    {
        $orgId = $organization instanceof Organization ? $organization->id : $organization;

        app(RoleProvisioner::class)->provision($orgId);

        app(CurrentOrganization::class)->run($orgId, function () {
            foreach (self::DEPARTMENTS as [$code, $name]) {
                Department::firstOrCreate(['code' => $code], ['name' => $name]);
            }
            foreach (self::LEAD_SOURCES as $name) {
                LeadSource::firstOrCreate(['name' => $name]);
            }
            foreach (self::STAGES as $i => [$slug, $name, $prob, $color, $won, $lost]) {
                // Chỉ tạo giai đoạn còn thiếu — không ghi đè giai đoạn tổ chức đã tự chỉnh
                PipelineStage::firstOrCreate(['slug' => $slug], [
                    'name' => $name, 'position' => $i + 1, 'probability' => $prob, 'color' => $color,
                    'is_won' => $won, 'is_lost' => $lost,
                ]);
            }
            foreach (self::PRODUCT_CATEGORIES as $name) {
                ProductCategory::firstOrCreate(['name' => $name]);
            }
            foreach (self::TAGS as [$name, $color]) {
                Tag::firstOrCreate(['name' => $name], ['color' => $color]);
            }
            // Giai đoạn lead & lý do thất bại: chỉ tạo khi tổ chức chưa có (không ghi đè cấu hình đã chỉnh)
            if (! \App\Models\LeadStage::exists()) {
                foreach (\App\Models\LeadStage::DEFAULTS as $i => [$key, $name, $color, $system, $qualified]) {
                    \App\Models\LeadStage::create(['key' => $key, 'name' => $name, 'color' => $color, 'position' => $i + 1, 'is_system' => $system, 'is_qualified' => $qualified]);
                }
            }
            if (! \App\Models\LostReason::exists()) {
                foreach (\App\Models\LostReason::DEFAULTS as $i => $name) {
                    \App\Models\LostReason::create(['name' => $name, 'applies_to' => 'both', 'position' => $i + 1]);
                }
            }
        });

        // Cài đặt riêng: tên công ty mặc định = tên tổ chức (không ghi đè nếu tổ chức đã tự đặt)
        $name = $organization instanceof Organization ? $organization->name : Organization::whereKey($orgId)->value('name');
        if ($name) {
            \App\Models\OrganizationSetting::firstOrCreate(['organization_id' => $orgId, 'key' => 'company_name'], ['value' => $name]);
            \App\Models\Setting::flushCache($orgId);
        }
    }
}
