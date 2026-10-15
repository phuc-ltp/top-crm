<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dòng chi tiết dùng chung cho báo giá / đơn hàng / hóa đơn. */
abstract class LineItem extends Model
{
    protected $fillable = ['product_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'line_total'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
