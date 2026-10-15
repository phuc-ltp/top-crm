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

    protected $fillable = [
        'sku', 'name', 'product_category_id', 'description', 'price', 'unit', 'status', 'image', 'is_demo',
    ];

    protected $casts = ['price' => 'decimal:2', 'is_demo' => 'boolean'];

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
