<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Nhóm làm việc trong tổ chức: trưởng nhóm, phòng ban (tùy chọn), thành viên (bước 4.2). */
class Team extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'description', 'leader_id', 'department_id'];

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members')->withTimestamps();
    }
}
