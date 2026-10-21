<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Giấy phép phần mềm (bước 5.6). Khóa bản quyền mã hóa trong DB, ẩn khỏi JSON / nhật ký; chỉ licenses.manage xem được
 * (mỗi lần xem ghi nhật ký).
 */
class SoftwareLicense extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    public const TYPES = [
        'subscription' => ['Thuê bao', 'primary'],
        'perpetual' => ['Vĩnh viễn', 'success'],
        'volume' => ['Theo số lượng', 'info'],
        'oem' => ['OEM (kèm máy)', 'secondary'],
        'free' => ['Miễn phí / mã nguồn mở', 'secondary'],
    ];

    public const CYCLES = [
        'monthly' => ['Hằng tháng', 'info'],
        'yearly' => ['Hằng năm', 'primary'],
        'one_time' => ['Một lần', 'secondary'],
    ];

    public const STATES = [
        'active' => ['Còn hạn', 'success'],
        'expiring' => ['Sắp hết hạn', 'warning'],
        'expired' => ['Đã hết hạn', 'danger'],
    ];

    protected $fillable = ['name', 'vendor', 'edition', 'license_type', 'license_key', 'seats', 'purchase_date', 'expires_at', 'cost', 'billing_cycle', 'supplier_id', 'owner_id', 'notes'];

    protected $hidden = ['license_key'];

    protected $casts = ['license_key' => 'encrypted', 'purchase_date' => 'date', 'expires_at' => 'date', 'cost' => 'decimal:2'];

    public function assignments()
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->hasMany(LicenseAssignment::class)->whereNull('released_at');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /** active | expiring (≤ 30 ngày) | expired. */
    public function getStateAttribute(): string
    {
        if (! $this->expires_at) {
            return 'active';
        }
        if ($this->expires_at->lt(today())) {
            return 'expired';
        }

        return $this->expires_at->lte(today()->addDays(30)) ? 'expiring' : 'active';
    }

    public function getDaysLeftAttribute(): ?int
    {
        return $this->expires_at ? (int) today()->diffInDays($this->expires_at, false) : null;
    }
}
