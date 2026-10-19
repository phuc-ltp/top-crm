<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dòng phiếu kho (thuộc tổ chức qua phiếu cha). Điều chỉnh: counted_quantity = số đếm thực tế, quantity = chênh lệch. */
class StockDocumentItem extends Model
{
    protected $fillable = ['stock_document_id', 'product_id', 'quantity', 'counted_quantity', 'unit_cost'];

    protected $casts = ['quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'unit_cost' => 'decimal:2'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
