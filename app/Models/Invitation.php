<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Link mời đăng ký tài khoản vào một tổ chức với một vai trò. Chỉ lưu bản băm của token —
 * link đầy đủ chỉ hiện một lần lúc tạo.
 */
class Invitation extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['role', 'note', 'max_uses', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    protected $hidden = ['token_hash'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'invited_via_id');
    }

    /** Tạo token ngẫu nhiên (trả về token gốc để dựng link; DB chỉ giữ bản băm). */
    public function issueToken(): string
    {
        $token = Str::random(40);
        $this->token_hash = self::hash($token);

        return $token;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Tìm lời mời theo token trên mọi tổ chức (trang đăng ký công khai, chưa có ngữ cảnh tổ chức). */
    public static function findByToken(string $token): ?self
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        return static::withoutGlobalScopes()->where('token_hash', self::hash($token))->first();
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture() && $this->uses < $this->max_uses
            && Organization::whereKey($this->organization_id)->where('status', 'active')->exists();
    }

    /** active | expired | used_up | revoked */
    public function getStateAttribute(): string
    {
        return match (true) {
            (bool) $this->revoked_at => 'revoked',
            $this->uses >= $this->max_uses => 'used_up',
            $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    public const STATES = [
        'active' => ['Đang mở', 'success'],
        'used_up' => ['Đã dùng hết lượt', 'secondary'],
        'expired' => ['Đã hết hạn', 'warning'],
        'revoked' => ['Đã thu hồi', 'danger'],
    ];
}
