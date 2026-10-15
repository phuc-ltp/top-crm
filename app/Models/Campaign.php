<?php

namespace App\Models;

use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'campaigns';

    protected array $ownerColumns = ['owner_id', 'created_by'];

    public const TYPES = [
        'email' => 'Email marketing', 'social' => 'Mạng xã hội', 'event' => 'Sự kiện', 'ads' => 'Quảng cáo',
        'content' => 'Nội dung', 'referral' => 'Giới thiệu', 'other' => 'Khác',
    ];

    public const STATUSES = [
        'planned' => ['Lên kế hoạch', 'secondary'],
        'active' => ['Đang chạy', 'success'],
        'paused' => ['Tạm dừng', 'warning'],
        'completed' => ['Hoàn thành', 'primary'],
        'cancelled' => ['Đã hủy', 'danger'],
    ];

    protected $fillable = [
        'name', 'type', 'start_date', 'end_date', 'budget', 'target_leads', 'status', 'description',
        'owner_id', 'created_by', 'is_demo',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'budget' => 'decimal:2', 'is_demo' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'campaign_leads')->withPivot('created_at');
    }

    /** Số lead thực tế gắn với chiến dịch. */
    public function getActualLeadsAttribute(): int
    {
        return (int) ($this->leads_count ?? $this->leads()->count());
    }

    public function getConvertedLeadsAttribute(): int
    {
        return (int) ($this->converted_leads_count ?? $this->leads()->where('status', 'converted')->count());
    }

    /** Tỷ lệ chuyển đổi = lead đã chuyển đổi / tổng lead của chiến dịch. */
    public function getConversionRateAttribute(): float
    {
        return $this->actual_leads > 0 ? round($this->converted_leads / $this->actual_leads * 100, 1) : 0.0;
    }

    public function getCostPerLeadAttribute(): float
    {
        return $this->actual_leads > 0 ? round((float) $this->budget / $this->actual_leads) : 0.0;
    }
}
