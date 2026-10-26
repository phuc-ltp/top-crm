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
        'lead_form_enabled' => '0',
        'lead_form_token' => '',
        'lead_form_assignee' => '',
        'lead_form_source' => '',
        'lead_form_thanks' => '',
    ];

    /**
     * Cài đặt RIÊNG của từng tổ chức (bảng organization_settings). Các khóa còn lại dùng chung toàn hệ thống
     * (trang đăng nhập, Google/Facebook, trang pháp lý, cờ khởi tạo, bắt buộc 2FA...).
     * Khóa của tổ chức KHÔNG lấy giá trị dùng chung làm dự phòng (tránh tổ chức mới mượn thông tin của tổ chức khác).
     */
    public const ORGANIZATION_KEYS = [
        'company_name', 'company_email', 'company_phone', 'company_address', 'company_logo', 'brand_color',
        'timezone', 'currency', 'date_format', 'default_tax_rate',
        'notify_lead_assigned', 'notify_task_due', 'notify_deal_stage', 'notify_ticket_new',
        // Form thu lead từ website (bước 2.4)
        'lead_form_enabled', 'lead_form_token', 'lead_form_assignee', 'lead_form_source', 'lead_form_thanks',
        'provisioned_at',
        // Kho hàng (bước 3.2)
        'inventory_allow_negative', 'notify_low_stock', 'inventory_track_orders',
        // Báo giá (bước 3.4)
        'quotation_discount_threshold',
        // Hóa đơn (bước 3.5): danh sách thuế suất VAT, VD "0,5,8,10"
        'tax_rates',
        // Thông tin trên chứng từ (hóa đơn / PDF)
        'company_tax_code', 'bank_name', 'bank_bin', 'bank_account_number', 'bank_account_name', 'invoice_terms',
        // SLA ticket (bước 4.3): giờ phản hồi đầu / giờ xử lý theo mức ưu tiên (trống = mặc định SlaService::DEFAULTS)
        'sla_response_urgent', 'sla_resolve_urgent', 'sla_response_high', 'sla_resolve_high',
        'sla_response_medium', 'sla_resolve_medium', 'sla_response_low', 'sla_resolve_low',
        // Chấm công (bước 5.2) — trống = mặc định AttendanceService::DEFAULTS
        'work_morning_start', 'work_morning_end', 'work_afternoon_start', 'work_afternoon_end', 'work_ot_start', 'work_ot_end',
        'work_days', 'attendance_late_grace', 'attendance_selfie_days',
        // Bảng lương (bước 5.4) — trống = mặc định PayrollService::DEFAULTS
        'payroll_insurance_enabled', 'payroll_pit_enabled', 'payroll_personal_deduction', 'payroll_dependent_deduction', 'payroll_insurance_cap',
        // Nghỉ phép — trống = mặc định LeaveService::DEFAULTS
        'leave_annual_days',
    ];

    private const CACHE_KEY = 'crm.settings';

    private static function orgCacheKey(int $organizationId): string
    {
        return self::CACHE_KEY.'.org.'.$organizationId;
    }

    private static function currentOrganizationId(): ?int
    {
        try {
            return app(\App\Support\Tenancy\CurrentOrganization::class)->id();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Toàn bộ cài đặt có hiệu lực cho tổ chức đang làm việc (hoặc tổ chức chỉ định). */
    public static function allCached(?int $organizationId = null): array
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            $stored = [];
        }
        $values = array_merge(self::DEFAULTS, array_filter(array_diff_key($stored, array_flip(self::ORGANIZATION_KEYS)), fn ($v) => $v !== null));

        $organizationId ??= self::currentOrganizationId();
        if ($organizationId) {
            try {
                $org = Cache::rememberForever(self::orgCacheKey($organizationId),
                    fn () => OrganizationSetting::where('organization_id', $organizationId)->pluck('value', 'key')->all());
            } catch (\Throwable) {
                $org = [];
            }
            $values = array_merge($values, array_filter(array_intersect_key($org, array_flip(self::ORGANIZATION_KEYS)), fn ($v) => $v !== null));
        }

        return $values;
    }

    public static function get(string $key, $default = null)
    {
        return static::allCached()[$key] ?? $default;
    }

    /** Lưu cài đặt: khóa của tổ chức ghi cho tổ chức đang làm việc (hoặc chỉ định), khóa chung ghi toàn hệ thống. */
    /** Danh sách thuế suất VAT của tổ chức (rỗng = nhập tự do). */
    public static function taxRates(): array
    {
        $raw = trim((string) self::get('tax_rates', ''));

        return $raw === '' ? [] : array_values(array_unique(array_map(fn ($v) => (float) trim($v), explode(',', $raw))));
    }

    public static function put(array $values, string $group = 'general', ?int $organizationId = null): void
    {
        $organizationId ??= self::currentOrganizationId();
        foreach ($values as $key => $value) {
            if (in_array($key, self::ORGANIZATION_KEYS, true)) {
                if (! $organizationId) {
                    throw new \RuntimeException('Không xác định được tổ chức để lưu cài đặt '.$key);
                }
                OrganizationSetting::updateOrCreate(['organization_id' => $organizationId, 'key' => $key], ['value' => $value]);
            } else {
                static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
            }
        }
        Cache::forget(self::CACHE_KEY);
        if ($organizationId) {
            Cache::forget(self::orgCacheKey($organizationId));
        }
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

    public static function flushCache(?int $organizationId = null): void
    {
        Cache::forget(self::CACHE_KEY);
        if ($organizationId ??= self::currentOrganizationId()) {
            Cache::forget(self::orgCacheKey($organizationId));
        }
    }
}
