<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    /** Nhật ký cấp nền tảng (vd: đăng nhập thất bại) có thể không gắn với tổ chức nào. */
    public function organizationIsOptional(): bool
    {
        return true;
    }

    public const ACTIONS = [
        'created' => ['Tạo mới', 'success'],
        'updated' => ['Cập nhật', 'primary'],
        'deleted' => ['Xóa', 'danger'],
        'login' => ['Đăng nhập', 'info'],
        'logout' => ['Đăng xuất', 'secondary'],
        'login_failed' => ['Đăng nhập lỗi', 'warning'],
        'converted' => ['Chuyển đổi', 'orange'],
        'stage_changed' => ['Đổi giai đoạn', 'orange'],
        'exported' => ['Xuất dữ liệu', 'secondary'],
        'imported' => ['Nhập dữ liệu', 'secondary'],
        'payment' => ['Thanh toán', 'success'],
        'assigned' => ['Phân công', 'primary'],
        'approved' => ['Duyệt', 'success'],
    ];

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address',
        'user_agent', 'created_at',
    ];

    protected $casts = ['properties' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
