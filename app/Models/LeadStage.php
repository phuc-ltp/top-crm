<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Giai đoạn lead của một tổ chức. "converted" (chỉ đạt qua chức năng chuyển đổi) và "lost" (cần lý do) là giai đoạn hệ thống:
 * đổi tên / màu được, không xóa được.
 */
class LeadStage extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const CONVERTED = 'converted';

    public const LOST = 'lost';

    /** Bảng màu badge cho phép. */
    public const COLORS = ['info' => 'Xanh dương nhạt', 'primary' => 'Cam', 'warning' => 'Vàng', 'orange' => 'Cam đậm', 'success' => 'Xanh lá',
        'danger' => 'Đỏ', 'purple' => 'Tím', 'secondary' => 'Xám'];

    /** Giá trị mặc định khi khởi tạo tổ chức: [key, tên, màu, hệ thống, đủ điều kiện]. */
    public const DEFAULTS = [
        ['new', 'Mới', 'info', false, false],
        ['contacted', 'Đã liên hệ', 'primary', false, false],
        ['qualified', 'Đủ điều kiện', 'warning', false, true],
        ['proposal', 'Đàm phán', 'orange', false, false],
        ['converted', 'Đã chuyển đổi', 'success', true, false],
        ['lost', 'Thất bại', 'danger', true, false],
    ];

    protected $fillable = ['key', 'name', 'color', 'position', 'is_system', 'is_qualified'];

    protected $casts = ['is_system' => 'boolean', 'is_qualified' => 'boolean'];

    protected static function booted(): void
    {
        $flush = fn () => app(\App\Support\LeadStages::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    /** Lead đang ở giai đoạn này (Lead có global scope tổ chức). */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'status', 'key');
    }

    /** Giai đoạn đang mở (chưa chuyển đổi / thất bại). */
    public function isOpen(): bool
    {
        return ! in_array($this->key, [self::CONVERTED, self::LOST], true);
    }
}
