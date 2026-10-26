<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use BelongsToOrganization, HasOwnership, LogsActivity, SoftDeletes;

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

    protected static function booted(): void
    {
        static::creating(function (Campaign $c) {
            $c->tracking_code ??= \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10));
        });
    }

    public function expenses()
    {
        return $this->hasMany(CampaignExpense::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Hiệu quả chiến dịch: chi phí thực tế (tổng khoản chi), doanh thu = giá trị cơ hội đã thắng gắn chiến dịch,
     * giá trị đang theo đuổi (cơ hội chưa đóng), ROI = (doanh thu − chi phí) / chi phí. ROI null khi chưa có chi phí.
     */
    public function performance(): array
    {
        $cost = (float) ($this->expenses_sum_amount ?? $this->expenses()->sum('amount'));
        $won = (float) ($this->won_value ?? $this->opportunities()->whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value'));
        $open = (float) ($this->open_value ?? $this->opportunities()->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))->sum('value'));

        return ['cost' => $cost, 'won' => $won, 'open' => $open, 'roi' => $cost > 0 ? round(($won - $cost) / $cost * 100, 1) : null,
            'budget_used' => (float) $this->budget > 0 ? round($cost / (float) $this->budget * 100, 1) : null];
    }

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

    /** Chi phí / lead: theo chi phí thực tế nếu đã ghi nhận, ngược lại theo ngân sách. */
    public function getCostPerLeadAttribute(): float
    {
        $cost = (float) ($this->expenses_sum_amount ?? $this->expenses()->sum('amount')) ?: (float) $this->budget;

        return $this->actual_leads > 0 ? round($cost / $this->actual_leads) : 0.0;
    }
}
