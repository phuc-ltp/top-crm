<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    public const STATUSES = [
        'active' => ['Đang hoạt động', 'success'],
        'locked' => ['Đã khóa', 'danger'],
    ];

    protected $fillable = [
        'name', 'email', 'username', 'password', 'phone', 'position', 'avatar', 'department_id', 'status', 'is_demo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
        ];
    }

    /** Tên đăng nhập lưu chữ thường; chuỗi rỗng = không có. */
    public function setUsernameAttribute($value): void
    {
        $value = trim((string) $value);
        $this->attributes['username'] = $value === '' ? null : mb_strtolower($value);
    }

    /** Các tổ chức mà người dùng là thành viên. */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class)->withPivot(['status', 'joined_at'])->withTimestamps();
    }

    public function currentOrganization()
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Thành viên của tổ chức (mặc định: tổ chức đang làm việc). $activeOnly = false để lấy cả thành viên bị tạm ngưng
     * (dùng cho trang quản lý thành viên); danh sách chọn người phụ trách luôn chỉ lấy thành viên đang hoạt động.
     */
    public function scopeInOrganization($query, ?int $organizationId = null, bool $activeOnly = true)
    {
        $organizationId ??= app(\App\Support\Tenancy\CurrentOrganization::class)->id();

        return $query->whereHas('organizations', fn ($q) => $q->whereKey($organizationId)
            ->when($activeOnly, fn ($q) => $q->where('organization_user.status', 'active'), fn ($q) => $q->whereIn('organization_user.status', ['active', 'suspended'])));
    }

    /** Trạng thái thành viên trong tổ chức (active / suspended / null nếu không thuộc tổ chức). */
    public function membershipStatus(?int $organizationId = null): ?string
    {
        $organizationId ??= app(\App\Support\Tenancy\CurrentOrganization::class)->id();

        return $this->organizations()->whereKey($organizationId)->first()?->pivot?->status;
    }

    public function belongsToOrganization(int $organizationId): bool
    {
        return $this->organizations()->whereKey($organizationId)->wherePivot('status', 'active')->exists();
    }

    protected static function booted(): void
    {
        // Tài khoản mới tự trở thành thành viên của tổ chức đang làm việc
        static::created(function (User $user) {
            $orgId = app(\App\Support\Tenancy\CurrentOrganization::class)->id();
            if ($orgId && ! $user->organizations()->whereKey($orgId)->exists()) {
                $user->organizations()->attach($orgId, ['status' => 'active', 'joined_at' => now()]);
            }
            if ($orgId && ! $user->current_organization_id) {
                $user->forceFill(['current_organization_id' => $orgId])->saveQuietly();
            }
        });
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function salesTargets()
    {
        return $this->hasMany(SalesTarget::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Quản trị nền tảng: toàn quyền trên mọi tổ chức (không phải vai trò spatie). */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /** Chủ sở hữu tổ chức đang làm việc. */
    public function isOrganizationOwner(): bool
    {
        return $this->hasRole(\App\Support\PermissionCatalog::OWNER);
    }

    /** Tài khoản được bảo vệ: quản trị nền tảng hoặc chủ sở hữu tổ chức. */
    public function isProtectedAccount(): bool
    {
        return $this->isPlatformAdmin() || $this->isOrganizationOwner();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        $last = end($parts) ?: '?';

        return mb_strtoupper(mb_substr($last, 0, 1).(count($parts) > 1 ? mb_substr($parts[0], 0, 1) : ''));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Email/thông báo gửi cho người dùng dùng ngôn ngữ họ đã chọn (null = mặc định hệ thống). */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
