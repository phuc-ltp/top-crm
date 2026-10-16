<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    public const STATUSES = [
        'active' => ['Đang kinh doanh', 'success'],
        'inactive' => ['Ngừng kinh doanh', 'secondary'],
    ];

    /** Dịch vụ không theo dõi tồn kho (Phase 3.2). */
    public const TYPES = [
        'product' => ['Sản phẩm', 'primary'],
        'service' => ['Dịch vụ', 'info'],
    ];

    protected $fillable = [
        'sku', 'type', 'name', 'product_category_id', 'supplier_id', 'description', 'price', 'purchase_price', 'unit', 'status', 'image', 'is_demo',
    ];

    protected $casts = ['price' => 'decimal:2', 'purchase_price' => 'decimal:2', 'is_demo' => 'boolean'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** Lợi nhuận gộp % trên giá bán = (giá bán − giá vốn) ÷ giá bán × 100; null khi thiếu dữ liệu. */
    public function getMarginPercentAttribute(): ?float
    {
        if ($this->purchase_price === null || \App\Support\Decimal::cmp($this->price, 0) <= 0) {
            return null;
        }

        return (float) \App\Support\Decimal::round(\App\Support\Decimal::div(\App\Support\Decimal::mul(\App\Support\Decimal::sub($this->price, $this->purchase_price), 100), $this->price), 1);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
