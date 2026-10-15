<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\LeadSource;
use App\Models\PipelineStage;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/** Dữ liệu cấu hình ban đầu (không phải dữ liệu demo). */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['BGD', 'Ban Giám đốc'], ['KD', 'Phòng Kinh doanh'], ['MKT', 'Phòng Marketing'],
            ['CSKH', 'Phòng Chăm sóc khách hàng'], ['KT', 'Phòng Kế toán'],
        ] as [$code, $name]) {
            Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (['Website', 'Facebook', 'Zalo', 'Google Ads', 'Giới thiệu', 'Sự kiện', 'Gọi điện', 'Email', 'Khác'] as $name) {
            LeadSource::firstOrCreate(['name' => $name]);
        }

        $stages = [
            ['new', 'Cơ hội mới', 10, '#6B7280', false, false],
            ['qualification', 'Đánh giá nhu cầu', 25, '#3B82F6', false, false],
            ['proposal', 'Gửi đề xuất', 50, '#F59E0B', false, false],
            ['negotiation', 'Đàm phán', 75, '#FF6B00', false, false],
            ['won', 'Thắng', 100, '#16A34A', true, false],
            ['lost', 'Thất bại', 0, '#DC2626', false, true],
        ];
        foreach ($stages as $i => [$slug, $name, $prob, $color, $won, $lost]) {
            PipelineStage::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'position' => $i + 1, 'probability' => $prob, 'color' => $color,
                'is_won' => $won, 'is_lost' => $lost,
            ]);
        }

        foreach (['Phần mềm', 'Dịch vụ', 'Phần cứng', 'Đào tạo', 'Gói hỗ trợ'] as $name) {
            ProductCategory::firstOrCreate(['name' => $name]);
        }

        foreach ([['VIP', '#FF6B00'], ['Doanh nghiệp lớn', '#2563EB'], ['Khách quen', '#16A34A'], ['Cần chăm sóc', '#F59E0B'], ['Đối tác', '#7C3AED']] as [$name, $color]) {
            Tag::firstOrCreate(['name' => $name], ['color' => $color]);
        }

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'notify_') ? 'notifications' : 'general']);
        }
        // Đổi tên thương hiệu cũ (chỉ khi người dùng chưa tự đặt tên khác)
        Setting::where('key', 'brand_color')->where('value', '#FF6B00')->update(['value' => '#D9823B']);
        Setting::where('key', 'company_name')->where('value', 'OrangeCRM Enterprise')->update(['value' => Setting::DEFAULTS['company_name']]);
        Setting::flushCache();
    }
}
