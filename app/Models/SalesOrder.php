<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\IsSalesDocument;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use BelongsToOrganization, HasCode, IsSalesDocument, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'orders';

    protected static string $codePrefix = 'DH';

    protected string $codeColumn = 'number';

    public const STATUSES = [
        'pending' => ['Chờ duyệt', 'warning'],
        'confirmed' => ['Đã duyệt', 'primary'],
        'processing' => ['Đang xử lý', 'info'],
        'completed' => ['Hoàn thành', 'success'],
        'cancelled' => ['Đã hủy', 'danger'],
    ];

    protected $fillable = [
        'number', 'customer_id', 'quotation_id', 'order_date', 'status', 'subtotal', 'discount_amount',
        'tax_rate', 'tax_amount', 'total', 'notes', 'created_by', 'assigned_to', 'approved_by', 'approved_at', 'is_demo',
    ];

    protected $casts = ['order_date' => 'date', 'approved_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
