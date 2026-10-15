<?php

namespace App\Support\Security;

use Illuminate\Validation\Rules\Password;

/**
 * Quy định mật khẩu chung của hệ thống. Chỉ áp dụng khi ĐẶT mật khẩu mới;
 * mật khẩu cũ vẫn đăng nhập được bình thường.
 * Giới hạn 128 ký tự để chặn chuỗi quá dài làm chậm việc băm mật khẩu.
 */
class PasswordPolicy
{
    public const MIN = 10;

    public const MAX = 128;

    public static function rule(): Password
    {
        return Password::min(self::MIN)->max(self::MAX)->letters()->mixedCase()->numbers();
    }
}
