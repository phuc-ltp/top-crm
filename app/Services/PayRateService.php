<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\PayGrade;
use App\Models\User;
use App\Support\Decimal;

/**
 * Đơn giá lương theo giờ (bước 5.1). Thứ tự ưu tiên:
 *   1. Đơn giá riêng trong hồ sơ nhân viên;
 *   2. Bậc lương chọn trong hồ sơ;
 *   3. Bậc lương mặc định của vai trò (vai trò có đơn giá cao nhất nếu người có nhiều vai trò);
 *   4. Bậc thấp nhất của tổ chức.
 * Đơn giá luôn nằm trong [crm.pay_rate_min, crm.pay_rate_max].
 */
class PayRateService
{
    /** 7 bậc mặc định: tên, đơn giá / giờ, vai trò dùng làm mặc định. */
    public static function defaults(): array
    {
        return [
            ['Bậc 7 — Chủ sở hữu / Giám đốc', 200000, ['Organization Owner']],
            ['Bậc 6 — Quản trị', 170000, ['Organization Administrator']],
            ['Bậc 5 — Quản lý', 140000, ['Sales Manager', 'Marketing Manager', 'Customer Support Manager', 'Finance Manager', 'Inventory Manager', 'Project Manager']],
            ['Bậc 4 — Trưởng nhóm', 100000, []],
            ['Bậc 3 — Chuyên viên cao cấp', 70000, ['Accountant']],
            ['Bậc 2 — Chuyên viên', 50000, ['Sales Employee', 'Marketing Employee', 'Support Agent']],
            ['Bậc 1 — Nhân viên', 35000, ['Employee', 'Read-only Auditor']],
        ];
    }

    /** Tạo 7 bậc mặc định cho tổ chức hiện tại nếu chưa có bậc nào. */
    public function ensureDefaults(): void
    {
        if (PayGrade::exists()) {
            return;
        }
        foreach (self::defaults() as $i => [$name, $rate, $roles]) {
            PayGrade::create(['name' => $name, 'hourly_rate' => $rate, 'roles' => $roles, 'position' => $i + 1]);
        }
    }

    public function rateFor(User $user): string
    {
        $this->ensureDefaults();
        $profile = EmployeeProfile::where('user_id', $user->id)->with('payGrade')->first();

        $rate = match (true) {
            $profile?->hourly_rate !== null => $profile->hourly_rate,
            $profile?->payGrade !== null => $profile->payGrade->hourly_rate,
            default => $this->gradeForRoles($user->getRoleNames()->all())?->hourly_rate
                ?? PayGrade::orderBy('hourly_rate')->value('hourly_rate'),
        };

        return Decimal::clamp($rate ?? config('crm.pay_rate_min'), config('crm.pay_rate_min'), config('crm.pay_rate_max'));
    }

    public function gradeForRoles(array $roles): ?PayGrade
    {
        return PayGrade::orderByDesc('hourly_rate')->get()->first(fn (PayGrade $g) => array_intersect($g->roles ?? [], $roles) !== []);
    }

    /** Nguồn của đơn giá (để giải thích trên giao diện). */
    public function sourceFor(User $user): string
    {
        $profile = EmployeeProfile::where('user_id', $user->id)->first();

        return match (true) {
            $profile?->hourly_rate !== null => __('Đơn giá riêng'),
            $profile?->pay_grade_id !== null => __('Theo bậc lương trong hồ sơ'),
            default => __('Theo vai trò'),
        };
    }
}
