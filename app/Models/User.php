<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
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

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
