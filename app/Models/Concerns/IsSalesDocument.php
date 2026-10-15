<?php

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Models\User;

/**
 * Thuộc tính chung cho Báo giá, Đơn hàng, Hóa đơn.
 * Model khai báo quan hệ items() trỏ tới bảng chi tiết.
 */
trait IsSalesDocument
{
    use HasOwnership;

    public function initializeIsSalesDocument(): void
    {
        $this->mergeCasts([
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'is_demo' => 'boolean',
        ]);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
