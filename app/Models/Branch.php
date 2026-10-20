<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Chi nhánh / văn phòng của tổ chức (bước 4.2). */
class Branch extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'code', 'address', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function profiles()
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
