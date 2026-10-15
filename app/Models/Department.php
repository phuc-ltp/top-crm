<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'code', 'description'];

    /** Thành viên thuộc phòng ban (phòng ban gắn với tư cách thành viên trong tổ chức của phòng ban). */
    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_user', 'department_id', 'user_id');
    }
}
