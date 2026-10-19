<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Sổ kho — nhật ký BẤT BIẾN mọi thay đổi tồn kho (số lượng có dấu: + vào, − ra) và số dư sau phát sinh.
 * Không có cập nhật / xóa.
 */
class StockMovement extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    public const TYPES = [
        'receipt' => ['Nhập kho', 'success'],
        'issue' => ['Xuất kho', 'warning'],
        'adjustment_in' => ['Điều chỉnh tăng', 'info'],
        'adjustment_out' => ['Điều chỉnh giảm', 'danger'],
        'transfer_in' => ['Chuyển đến', 'primary'],
        'transfer_out' => ['Chuyển đi', 'secondary'],
    ];

    protected $fillable = ['product_id', 'warehouse_id', 'stock_document_id', 'type', 'quantity', 'unit_cost', 'balance_after', 'created_by'];

    protected $casts = ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'balance_after' => 'decimal:3'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Sổ kho không được sửa.'));
        static::deleting(fn () => throw new \LogicException('Sổ kho không được xóa.'));
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function document()
    {
        return $this->belongsTo(StockDocument::class, 'stock_document_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
