<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Setting;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Database\Seeder;

/** Dữ liệu cấu hình ban đầu (không phải dữ liệu demo). */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Dữ liệu cấu hình riêng của từng tổ chức (phòng ban, nguồn, giai đoạn, danh mục, thẻ, vai trò)
        foreach (Organization::pluck('id') as $orgId) {
            app(OrganizationProvisioner::class)->provision((int) $orgId);
        }

        // Cài đặt chung của hệ thống
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'notify_') ? 'notifications' : 'general']);
        }
        // Đổi tên thương hiệu cũ (chỉ khi người dùng chưa tự đặt tên khác)
        Setting::where('key', 'brand_color')->where('value', '#FF6B00')->update(['value' => '#D9823B']);
        Setting::where('key', 'company_name')->where('value', 'OrangeCRM Enterprise')->update(['value' => Setting::DEFAULTS['company_name']]);
        Setting::flushCache();
    }
}
