<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Bậc lương theo giờ (bước 5.1). "roles": các vai trò dùng bậc này làm mặc định khi hồ sơ chưa chọn bậc. */
class PayGrade extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'hourly_rate', 'roles', 'position', 'description'];

    protected $casts = ['hourly_rate' => 'decimal:2', 'roles' => 'array', 'position' => 'integer'];

    public function profiles()
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
