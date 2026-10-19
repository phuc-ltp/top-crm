<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\IsSalesDocument;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToOrganization, HasCode, IsSalesDocument, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'quotations';

    protected static string $codePrefix = 'BG';

    protected string $codeColumn = 'number';

    public const APPROVAL_STATUSES = [
        'pending' => ['Chờ duyệt chiết khấu', 'warning'],
        'approved' => ['Đã duyệt chiết khấu', 'success'],
        'rejected' => ['Từ chối duyệt', 'danger'],
    ];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public const STATUSES = [
        'draft' => ['Nháp', 'secondary'],
        'sent' => ['Đã gửi', 'primary'],
        'accepted' => ['Được chấp nhận', 'success'],
        'rejected' => ['Bị từ chối', 'danger'],
        'expired' => ['Hết hạn', 'warning'],
    ];

    protected $fillable = [
        'number', 'customer_id', 'opportunity_id', 'issue_date', 'valid_until', 'status', 'subtotal',
        'discount_amount', 'tax_rate', 'tax_amount', 'total', 'notes', 'created_by', 'assigned_to', 'is_demo',
        'approval_status', 'approved_by', 'approved_at', 'approval_note',
    ];

    protected $casts = ['issue_date' => 'date', 'valid_until' => 'date'];

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }
}
