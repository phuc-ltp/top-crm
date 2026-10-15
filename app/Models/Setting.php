<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public const DEFAULTS = [
        'company_name' => 'TOP CRM',
        // Thương hiệu & trang đăng nhập
        'brand_name' => 'TOP',
        'brand_tagline' => 'TECHNOLOGY OF PROWESS',
        'brand_slogan' => 'TOP CRM - Quản lý khách hàng, Kiến tạo tăng trưởng',
        'login_headline' => 'Kết nối khách hàng',
        'login_headline_accent' => 'Kiến tạo tăng trưởng',
        'login_description' => 'TOP CRM giúp doanh nghiệp quản lý khách hàng, tối ưu quy trình bán hàng và nâng cao hiệu quả kinh doanh với công nghệ hiện đại.',
        'login_stats' => "10K+|Khách hàng tin dùng\n300%|Tăng hiệu suất\n99.9%|Bảo mật dữ liệu\n24/7|Hỗ trợ khách hàng",
        'login_background' => '',
        'copyright_owner' => 'Trịnh Phú Phúc',
        'copyright_text' => 'TOP — Technology of Prowess',
        'page_terms' => '',
        'page_privacy' => '',
        'page_support' => '',
        // Đăng nhập mạng xã hội (OAuth) — để trống = chưa bật
        'google_client_id' => '',
        'google_client_secret' => '',
        'facebook_client_id' => '',
        'facebook_client_secret' => '',
        'company_email' => '',
        'company_phone' => '',
        'company_address' => '',
        'company_logo' => '',
        'brand_color' => '#D9823B',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'currency' => 'VND',
        'date_format' => 'd/m/Y',
        'language' => 'vi',
        'default_tax_rate' => '10',
        'notify_lead_assigned' => '1',
        'notify_task_due' => '1',
        'notify_deal_stage' => '1',
        'notify_ticket_new' => '1',
        // Bảo mật: bắt buộc xác thực 2 lớp cho tài khoản quản trị (chỉ quản trị nền tảng đổi được)
        'require_2fa_admins' => '0',
    ];

    private const CACHE_KEY = 'crm.settings';

    public static function allCached(): array
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            $stored = [];
        }

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($v) => $v !== null));
    }

    public static function get(string $key, $default = null)
    {
        return static::allCached()[$key] ?? $default;
    }

    public static function put(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** Giá trị bí mật (OAuth secret) được lưu mã hóa bằng APP_KEY. */
    public static function secret(string $key): ?string
    {
        $value = static::get($key);
        if (! $value) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Các chỉ số hiển thị ở trang đăng nhập: mỗi dòng "giá trị|nhãn". */
    public static function loginStats(): array
    {
        return collect(preg_split('/\R/', (string) static::get('login_stats')))
            ->map(fn ($line) => array_map('trim', explode('|', $line, 2)))
            ->filter(fn ($p) => count($p) === 2 && $p[0] !== '')
            ->take(4)->values()->all();
    }

    public static function socialEnabled(string $provider): bool
    {
        return static::get($provider.'_client_id') && static::secret($provider.'_client_secret');
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
