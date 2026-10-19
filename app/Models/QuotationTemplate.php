<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Mẫu báo giá: dòng sản phẩm, thuế, điều khoản soạn sẵn (theo tổ chức). */
class QuotationTemplate extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'tax_rate', 'notes', 'items', 'is_active', 'created_by'];

    protected $casts = ['items' => 'array', 'tax_rate' => 'decimal:2', 'is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
