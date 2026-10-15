<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Luật kiểm tra dữ liệu trong phạm vi tổ chức đang làm việc.
 * Dùng thay cho 'exists:customers,id' / Rule::unique(...) để không thể tham chiếu hay va chạm với dữ liệu tổ chức khác.
 */
class TenantRule
{
    private static function org(): int
    {
        return (int) app(CurrentOrganization::class)->id();
    }

    /** Bản ghi phải tồn tại TRONG tổ chức hiện tại (và chưa bị xóa mềm nếu bảng có deleted_at). */
    public static function exists(string $table, string $column = 'id', bool $withTrashed = false): Exists
    {
        $rule = Rule::exists($table, $column)->where('organization_id', self::org());

        return $withTrashed || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at') ? $rule : $rule->whereNull('deleted_at');
    }

    /** Giá trị phải duy nhất TRONG tổ chức hiện tại. */
    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('organization_id', self::org());
    }

    /** Người dùng phải là thành viên đang hoạt động của tổ chức hiện tại. */
    public static function member(): Exists
    {
        return Rule::exists('organization_user', 'user_id')
            ->where('organization_id', self::org())
            ->where('status', 'active');
    }
}
