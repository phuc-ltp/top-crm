<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Nhật ký luân chuyển tài sản (bước 5.5) — bất biến: không sửa, không xóa. */
class AssetEvent extends Model
{
    use BelongsToOrganization;

    public const TYPES = [
        'created' => ['Nhập tài sản', 'secondary', 'bi-plus-circle'],
        'assigned' => ['Cấp phát', 'primary', 'bi-person-check'],
        'recalled' => ['Thu hồi', 'secondary', 'bi-arrow-return-left'],
        'borrowed' => ['Cho mượn', 'info', 'bi-box-arrow-up-right'],
        'returned' => ['Trả lại', 'success', 'bi-box-arrow-in-down-left'],
        'maintenance' => ['Đưa đi bảo trì', 'warning', 'bi-tools'],
        'repaired' => ['Bảo trì xong', 'success', 'bi-wrench-adjustable'],
        'status' => ['Đổi tình trạng', 'danger', 'bi-exclamation-triangle'],
        'checked' => ['Kiểm kê', 'info', 'bi-clipboard-check'],
        'disposed' => ['Thanh lý', 'secondary', 'bi-trash3'],
    ];

    public const CONDITIONS = [
        'good' => ['Tốt', 'success'],
        'fair' => ['Bình thường', 'info'],
        'damaged' => ['Hư hỏng', 'danger'],
    ];

    protected $fillable = ['asset_id', 'type', 'user_id', 'target_user_id', 'from_status', 'to_status', 'cost', 'condition', 'note', 'asset_audit_id', 'occurred_at'];

    protected $casts = ['occurred_at' => 'datetime', 'cost' => 'decimal:2'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Nhật ký tài sản không được sửa.'));
        static::deleting(fn () => throw new \LogicException('Nhật ký tài sản không được xóa.'));
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
