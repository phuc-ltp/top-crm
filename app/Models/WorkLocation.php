<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Văn phòng / địa điểm chấm công: tọa độ và bán kính cho phép (bước 5.2). */
class WorkLocation extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'address', 'latitude', 'longitude', 'radius_m', 'is_active'];

    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'radius_m' => 'integer', 'is_active' => 'boolean'];
}
