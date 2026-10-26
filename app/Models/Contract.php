<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Hợp đồng khách hàng. Tình trạng hiệu lực (state) tính từ trạng thái + ngày hiệu lực / hết hạn. */
class Contract extends Model
{
    use BelongsToOrganization, HasCode, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $codePrefix = 'HDG';

    protected static string $permissionModule = 'contracts';

    public const TYPES = [
        'sale' => ['Mua bán', 'primary'],
        'service' => ['Dịch vụ', 'info'],
        'maintenance' => ['Bảo trì / hỗ trợ', 'warning'],
        'partnership' => ['Hợp tác / đại lý', 'success'],
        'other' => ['Khác', 'secondary'],
    ];

    public const STATUSES = [
        'draft' => ['Nháp', 'secondary'],
        'signed' => ['Đã ký', 'primary'],
        'terminated' => ['Đã chấm dứt', 'dark'],
    ];

    /** Tình trạng hiệu lực hiển thị. */
    public const STATES = [
        'draft' => ['Nháp', 'secondary'],
        'pending' => ['Chưa đến ngày hiệu lực', 'info'],
        'active' => ['Đang hiệu lực', 'success'],
        'expiring' => ['Sắp hết hạn', 'warning'],
        'expired' => ['Đã hết hạn', 'danger'],
        'terminated' => ['Đã chấm dứt', 'dark'],
    ];

    protected $fillable = [
        'title', 'customer_id', 'contact_id', 'opportunity_id', 'type', 'value', 'signed_date', 'start_date', 'end_date',
        'status', 'auto_renew', 'terms', 'assigned_to', 'created_by', 'is_demo',
    ];

    protected $casts = [
        'value' => 'decimal:2', 'signed_date' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'terminated_on' => 'date',
        'auto_renew' => 'boolean', 'is_demo' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(Contract::class, 'renewed_from_id')->withTrashed();
    }

    public function renewals()
    {
        return $this->hasMany(Contract::class, 'renewed_from_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'related_id')->where('related_type', 'contract');
    }

    public function getStateAttribute(): string
    {
        if ($this->status === 'terminated') {
            return 'terminated';
        }
        if ($this->status === 'draft') {
            return 'draft';
        }
        if ($this->start_date && $this->start_date->gt(today())) {
            return 'pending';
        }
        if ($this->end_date && $this->end_date->lt(today())) {
            return 'expired';
        }

        return $this->end_date && $this->end_date->lte(today()->addDays(30)) ? 'expiring' : 'active';
    }

    public function getDaysLeftAttribute(): ?int
    {
        return $this->end_date ? (int) today()->diffInDays($this->end_date, false) : null;
    }
}
