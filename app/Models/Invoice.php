<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\IsSalesDocument;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToOrganization, HasCode, IsSalesDocument, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'invoices';

    protected static string $codePrefix = 'HD';

    protected string $codeColumn = 'number';

    public const STATUSES = [
        'draft' => ['Nháp', 'secondary'],
        'issued' => ['Đã phát hành', 'primary'],
        'cancelled' => ['Đã hủy', 'danger'],
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => ['Chưa thanh toán', 'danger'],
        'partial' => ['Thanh toán một phần', 'warning'],
        'paid' => ['Đã thanh toán', 'success'],
    ];

    protected $fillable = [
        'number', 'customer_id', 'sales_order_id', 'issue_date', 'due_date', 'status', 'payment_status',
        'subtotal', 'discount_amount', 'tax_rate', 'tax_amount', 'total', 'amount_paid', 'notes',
        'created_by', 'assigned_to', 'is_demo',
    ];

    protected $casts = ['issue_date' => 'date', 'due_date' => 'date', 'amount_paid' => 'decimal:2'];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest('payment_date');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, round((float) $this->total - (float) $this->amount_paid, 2));
    }

    /** Quá hạn: đã phát hành, chưa trả đủ và quá ngày đến hạn. */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'issued' && $this->payment_status !== 'paid'
            && $this->due_date && $this->due_date->isBefore(today());
    }
}
