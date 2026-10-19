<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Phiếu kho đã ghi sổ: nhập (PN), xuất (PX), điều chỉnh kiểm kê (DC), chuyển kho (CK).
 * Phiếu là chứng từ bất biến — không sửa / xóa; sai thì lập phiếu điều chỉnh.
 */
class StockDocument extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const TYPES = [
        'receipt' => ['Phiếu nhập kho', 'success', 'PN'],
        'issue' => ['Phiếu xuất kho', 'warning', 'PX'],
        'adjustment' => ['Phiếu điều chỉnh (kiểm kê)', 'info', 'DC'],
        'transfer' => ['Phiếu chuyển kho', 'primary', 'CK'],
    ];

    protected $fillable = ['number', 'type', 'warehouse_id', 'to_warehouse_id', 'supplier_id', 'document_date', 'reference', 'notes', 'created_by', 'is_demo'];

    protected $casts = ['document_date' => 'date', 'is_demo' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (StockDocument $d) {
            $d->number ??= 'TMP-'.\Illuminate\Support\Str::random(20);
        });
        static::created(function (StockDocument $d) {
            if (str_starts_with($d->number, 'TMP-')) {
                $d->number = self::TYPES[$d->type][2].str_pad((string) $d->id, 5, '0', STR_PAD_LEFT);
                $d->saveQuietly();
            }
        });
        static::updating(function (StockDocument $d) {
            // Chỉ cho phép gán số phiếu ngay sau khi tạo; mọi thay đổi khác bị chặn
            if (array_diff(array_keys($d->getDirty()), ['number', 'updated_at'])) {
                throw new \LogicException('Phiếu kho đã ghi sổ không được sửa.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Phiếu kho đã ghi sổ không được xóa.'));
    }

    public function items()
    {
        return $this->hasMany(StockDocumentItem::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
