<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToOrganization, HasCode, HasFactory, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'leads';

    protected static string $codePrefix = 'LD';

    public const STATUSES = [
        'new' => ['Mới', 'info'],
        'contacted' => ['Đã liên hệ', 'primary'],
        'qualified' => ['Đủ điều kiện', 'warning'],
        'proposal' => ['Đàm phán', 'orange'],
        'converted' => ['Đã chuyển đổi', 'success'],
        'lost' => ['Thất bại', 'danger'],
    ];

    protected $fillable = [
        'code', 'name', 'company', 'email', 'phone', 'lead_source_id', 'status', 'score', 'estimated_value',
        'assigned_to', 'created_by', 'converted_customer_id', 'converted_opportunity_id', 'converted_at',
        'lost_reason_id', 'lost_note', 'marketing_consent', 'marketing_consent_source', 'notes', 'is_demo', 'utm_source', 'utm_medium', 'utm_campaign',
    ];

    protected $casts = [
        'converted_at' => 'datetime', 'qualified_at' => 'datetime', 'marketing_consent' => 'boolean', 'marketing_consent_at' => 'datetime',
        'estimated_value' => 'decimal:2', 'score' => 'integer', 'is_demo' => 'boolean',
    ];

    /**
     * Giai đoạn lead của tổ chức đang làm việc: [key => [tên, màu badge]], theo thứ tự.
     * Dùng thay cho hằng STATUSES (giờ chỉ là giá trị mặc định khi chưa cấu hình).
     */
    public static function statuses(): array
    {
        return app(\App\Support\LeadStages::class)->map();
    }

    /** Các mã giai đoạn đang mở (chưa chuyển đổi / thất bại). */
    public static function openStatuses(): array
    {
        return array_values(array_diff(array_keys(self::statuses()), [LeadStage::CONVERTED, LeadStage::LOST]));
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            if ($lead->isDirty('status')) {
                // Lần đầu vào giai đoạn "đủ điều kiện" → ghi thời điểm (phân tích thời gian xử lý lead)
                if (! $lead->qualified_at && app(\App\Support\LeadStages::class)->isQualified($lead->status)) {
                    $lead->qualified_at = now();
                }
                // Rời giai đoạn thất bại → bỏ lý do mất
                if ($lead->status !== LeadStage::LOST) {
                    $lead->lost_reason_id = null;
                    $lead->lost_note = null;
                }
            }
            if ($lead->isDirty('marketing_consent')) {
                $lead->marketing_consent_at = $lead->marketing_consent ? now() : null;
            }
        });
    }

    public function lostReason()
    {
        return $this->belongsTo(LostReason::class);
    }

    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_leads')->withPivot('created_at');
    }

    public function convertedCustomer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function convertedOpportunity()
    {
        return $this->belongsTo(Opportunity::class, 'converted_opportunity_id');
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable')->latest('occurred_at');
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function getScoreLevelAttribute(): array
    {
        return match (true) {
            $this->score >= 70 => [__('Nóng'), 'danger'],
            $this->score >= 40 => [__('Ấm'), 'warning'],
            default => [__('Lạnh'), 'info'],
        };
    }
}
