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

class User extends Authenticatable implements HasLocalePreference, \Illuminate\Contracts\Auth\MustVerifyEmail
{
    use \Illuminate\Auth\MustVerifyEmail, \Laravel\Sanctum\HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    public const STATUSES = [
        'active' => ['Đang hoạt động', 'success'],
        'locked' => ['Đã khóa', 'danger'],
    ];

    protected $fillable = [
        'name', 'email', 'username', 'password', 'phone', 'position', 'avatar', 'status', 'is_demo',   // phòng ban: theo tổ chức (assignDepartment)
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_step'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
            'email_otp_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'notification_preferences' => 'array',
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
        return $this->belongsToMany(Organization::class)->withPivot(['status', 'joined_at', 'department_id'])->withTimestamps();
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

        // Xóa tài khoản (kể cả xóa mềm) → thu hồi mọi khóa API của tài khoản
        static::deleted(fn (User $user) => $user->tokens()->delete());
    }

    /** Mã phòng ban của người dùng trong một tổ chức (mặc định: tổ chức đang làm việc). */
    public function departmentIdIn(?int $organizationId = null): ?int
    {
        $organizationId ??= app(\App\Support\Tenancy\CurrentOrganization::class)->id();
        $membership = $this->relationLoaded('organizations')
            ? $this->organizations->firstWhere('id', $organizationId)
            : $this->organizations()->whereKey($organizationId)->first();
        $id = $membership?->pivot?->department_id;

        return $id ? (int) $id : null;
    }

    /**
     * Phòng ban trong tổ chức đang làm việc ($user->department). Phòng ban thuộc tổ chức khác không bao giờ được trả về
     * vì Department có global scope theo tổ chức.
     */
    protected function department(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(function () {
            $id = $this->departmentIdIn();

            return $id ? Department::find($id) : null;
        })->shouldCache();
    }

    /** Gán phòng ban cho người dùng trong một tổ chức (phải là thành viên). */
    public function assignDepartment(?int $departmentId, ?int $organizationId = null): void
    {
        $organizationId ??= app(\App\Support\Tenancy\CurrentOrganization::class)->id();
        $this->organizations()->updateExistingPivot($organizationId, ['department_id' => $departmentId]);
        $this->unsetRelation('organizations');
    }

    /** Hồ sơ nhân viên trong tổ chức đang làm việc (EmployeeProfile có global scope theo tổ chức). */
    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /** Các nhóm (của tổ chức đang làm việc) người dùng tham gia. */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')->withTimestamps();
    }

    public function salesTargets()
    {
        return $this->hasMany(SalesTarget::class);
    }

    /** Đã bật xác thực 2 lớp (đã quét mã và xác nhận thành công). */
    /** Bước 2 bằng mã gửi qua email (khi không dùng ứng dụng xác thực): tự bật, hoặc quản trị bắt buộc toàn hệ thống. */
    public function usesEmailOtp(): bool
    {
        return ! $this->hasTwoFactorEnabled() && filled($this->email)
            && ($this->email_otp_enabled ||\App\Models\Setting::get('require_email_otp', '0') === '1');
    }

    /** Đăng nhập cần bước 2 (ứng dụng xác thực hoặc mã email). */
    public function needsSecondFactor(): bool
    {
        return $this->hasTwoFactorEnabled() || $this->usesEmailOtp();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && ! empty($this->two_factor_secret);
    }

    public function loginEvents()
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
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

    /** Cấp bậc trong tổ chức đang làm việc (0 = quản trị nền tảng, 1 = chủ sở hữu … 5 = chỉ xem); không có vai trò = 99. */
    public function hierarchyLevel(): int
    {
        if ($this->isPlatformAdmin()) {
            return 0;
        }
        $levels = $this->getRoleNames()->map(fn ($r) => \App\Support\PermissionCatalog::level($r));

        return $levels->isEmpty() ? 99 : (int) $levels->min();
    }

    /** Được quản lý tài khoản $target: quản trị nền tảng / chủ sở hữu quản lý mọi người (trừ quản trị nền tảng); còn lại phải cấp cao hơn. */
    public function outranks(User $target): bool
    {
        if ($target->isPlatformAdmin()) {
            return false;
        }
        if ($this->isPlatformAdmin() || $this->isOrganizationOwner()) {
            return true;
        }

        return $this->hierarchyLevel() < $target->hierarchyLevel();
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
