<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Bảng giá theo nhóm khách hàng (giá riêng từng sản phẩm). Sản phẩm không có trong bảng giá dùng giá bán chuẩn. */
class PriceList extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** [product_id => giá] của bảng giá đang hoạt động; rỗng nếu bảng giá tắt. */
    public function priceMap(): array
    {
        return $this->is_active ? $this->items()->pluck('price', 'product_id')->map(fn ($p) => (string) $p)->all() : [];
    }
}
