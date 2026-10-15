<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tổ chức (doanh nghiệp) sử dụng TOP CRM. Mọi dữ liệu nghiệp vụ thuộc về đúng một tổ chức. */
class Organization extends Model
{
    use SoftDeletes;

    public const DEFAULT_ID = 1;

    public const STATUSES = [
        'active' => ['Đang hoạt động', 'success'],
        'suspended' => ['Tạm ngưng', 'danger'],
    ];

    protected $fillable = ['name', 'slug', 'status', 'owner_id'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['status', 'joined_at'])->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
