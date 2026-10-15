<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Khóa API cá nhân (Sanctum) gắn với một tổ chức.
 * Quyền thực tế = (quyền của người tạo trong tổ chức) ∩ (phạm vi chọn khi tạo khóa).
 * Không dùng global scope tổ chức: Sanctum phải tìm được khóa TRƯỚC khi biết tổ chức.
 */
class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    /** Phạm vi (ability) của khóa → nhãn hiển thị. */
    public const SCOPES = [
        'stats:read' => 'Đọc số liệu tổng quan',
        'customers:read' => 'Đọc khách hàng',
        'customers:write' => 'Tạo / sửa / lưu trữ khách hàng',
        'leads:read' => 'Đọc leads',
        'leads:write' => 'Tạo / sửa leads',
        'opportunities:read' => 'Đọc cơ hội bán hàng',
    ];

    /** Phạm vi chỉ được chọn khi người tạo có ít nhất một quyền tương ứng trong tổ chức. */
    public const SCOPE_PERMISSIONS = [
        'stats:read' => ['dashboard.view'],
        'customers:read' => ['customers.view'],
        'customers:write' => ['customers.create', 'customers.update', 'customers.delete'],
        'leads:read' => ['leads.view'],
        'leads:write' => ['leads.create', 'leads.update'],
        'opportunities:read' => ['opportunities.view'],
    ];

    /** Các phạm vi người dùng được phép cấp cho khóa (theo quyền hiện tại trong tổ chức đang làm việc). */
    public static function grantableScopes(User $user): array
    {
        return array_keys(array_filter(self::SCOPE_PERMISSIONS, fn ($perms) => collect($perms)->contains(fn ($p) => $user->can($p))));
    }

    /** Thời hạn được phép (ngày). Không có khóa vĩnh viễn. */
    public const EXPIRY_DAYS = [30, 90, 180, 365];

    public const DEFAULT_EXPIRY_DAYS = 90;

    /** Số khóa còn hiệu lực tối đa của một người trong một tổ chức. */
    public const MAX_ACTIVE_PER_USER = 10;

    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'organization_id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
