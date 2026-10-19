<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Giá của một sản phẩm trong một bảng giá (thuộc tổ chức qua bảng giá cha). */
class PriceListItem extends Model
{
    protected $fillable = ['price_list_id', 'product_id', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
