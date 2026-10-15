<?php

namespace App\Models;

use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasCode, HasFactory, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'leads';

    protected static string $codePrefix = 'LD';

    public const STATUSES = [
        'new' => ['Mới', 'info'],
        'contacted' => ['Đã liên hệ', 'primary'],
        'qualified' => ['Đủ điều kiện', 'warning'],
        'proposal' => ['Đã gửi đề xuất', 'orange'],
        'converted' => ['Đã chuyển đổi', 'success'],
        'lost' => ['Thất bại', 'danger'],
    ];

    protected $fillable = [
        'code', 'name', 'company', 'email', 'phone', 'lead_source_id', 'status', 'score', 'estimated_value',
        'assigned_to', 'created_by', 'converted_customer_id', 'converted_opportunity_id', 'converted_at',
        'notes', 'is_demo',
    ];

    protected $casts = [
        'converted_at' => 'datetime', 'estimated_value' => 'decimal:2', 'score' => 'integer', 'is_demo' => 'boolean',
    ];

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
