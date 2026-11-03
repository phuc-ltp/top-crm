<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lịch sử đăng nhập của TÀI KHOẢN (không thuộc tổ chức nào). Chỉ chủ tài khoản xem được.
 * Không lưu chuỗi người dùng gõ vào ô đăng nhập khi sai (có thể là mật khẩu gõ nhầm ô).
 */
class LoginEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Số ngày lưu lịch sử; cũ hơn sẽ tự xóa (lệnh model:prune chạy hằng ngày). */
    public const RETENTION_DAYS = 180;

    public const EVENTS = [
        'login' => ['Đăng nhập thành công', 'success'],
        'failed' => ['Sai mật khẩu', 'danger'],
        'two_factor_failed' => ['Sai mã xác thực 2 lớp', 'danger'],
        'blocked' => ['Tài khoản bị khóa', 'secondary'],
        'locked_out' => ['Tạm khóa do thử quá nhiều lần', 'warning'],
    ];

    public const METHODS = [
        'password' => 'Mật khẩu',
        'google' => 'Google',
        'facebook' => 'Facebook',
        'totp' => 'Mã xác thực 2 lớp',
        'recovery_code' => 'Mã khôi phục',
        'email_otp' => 'Mã xác thực qua email',
        'invitation' => 'Đăng ký qua link mời',
    ];

    protected $fillable = ['user_id', 'event', 'method', 'ip_address', 'user_agent', 'device_hash', 'is_new_device'];

    protected $hidden = ['device_hash'];

    protected function casts(): array
    {
        return ['is_new_device' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
