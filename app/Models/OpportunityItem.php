<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sản phẩm / dịch vụ dự kiến bán trong một cơ hội. Thành tiền luôn tính lại ở backend. */
class OpportunityItem extends Model
{
    protected $fillable = ['product_id', 'name', 'quantity', 'unit_price', 'discount_percent', 'line_total', 'position'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'discount_percent' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Thành tiền = SL × đơn giá × (1 − chiết khấu%), làm tròn 2 chữ số. */
    public static function lineTotal(float $quantity, float $unitPrice, float $discountPercent): float
    {
        return round($quantity * $unitPrice * (1 - min(100, max(0, $discountPercent)) / 100), 2);
    }
}
