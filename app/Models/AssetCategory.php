<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Nhóm tài sản (bước 5.5), kèm số tháng khấu hao mặc định. */
class AssetCategory extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'depreciation_months', 'description'];

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
