<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Kho hàng (theo tổ chức). Kho đã có phát sinh không xóa được — chỉ ngừng sử dụng. */
class Warehouse extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['code', 'name', 'address', 'is_default', 'is_active'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function levels()
    {
        return $this->hasMany(StockLevel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::active()->orderBy('id')->first();
    }
}
