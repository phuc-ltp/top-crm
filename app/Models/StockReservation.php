<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Hàng đang giữ cho một đơn hàng (tổng theo kho × sản phẩm nằm ở stock_levels.reserved). Chỉ OrderFulfillmentService thay đổi. */
class StockReservation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['sales_order_id', 'warehouse_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
