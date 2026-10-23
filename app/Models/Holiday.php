<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Ngày lễ của tổ chức (nghỉ có lương theo mặc định). */
class Holiday extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['date', 'name', 'is_paid'];

    protected $casts = ['date' => 'date', 'is_paid' => 'boolean'];
}
