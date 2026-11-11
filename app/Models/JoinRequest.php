<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Yêu cầu tham gia công ty (tạo ở trang "Tạo tài khoản", email đã xác thực) — chờ quản trị duyệt. */
class JoinRequest extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'email', 'phone', 'message', 'ip_address'];

    protected $hidden = ['password'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public static function statuses(): array
    {
        return [
            'pending' => ['Chờ duyệt', 'warning'],
            'approved' => ['Đã duyệt', 'success'],
            'rejected' => ['Đã từ chối', 'secondary'],
        ];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Có yêu cầu đang chờ duyệt cho email này (ở bất kỳ tổ chức nào) — dùng cho thông báo ở trang đăng nhập. */
    public static function pendingFor(string $email): bool
    {
        return static::withoutGlobalScopes()->where('email', mb_strtolower(trim($email)))->where('status', 'pending')->exists();
    }
}
