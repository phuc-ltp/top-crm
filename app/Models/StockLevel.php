<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Tồn kho của một sản phẩm tại một kho. Chỉ InventoryService được thay đổi (trong giao dịch, có khóa dòng),
 * luôn kèm một dòng sổ kho — số tồn = tổng phát sinh trong sổ kho.
 */
class StockLevel extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['warehouse_id', 'product_id', 'quantity', 'reserved'];

    protected $casts = ['quantity' => 'decimal:3', 'reserved' => 'decimal:3'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Có thể dùng = tồn thực − đang giữ cho đơn hàng. */
    public function getAvailableAttribute(): string
    {
        return \App\Support\Decimal::sub($this->quantity, $this->reserved);
    }
}
