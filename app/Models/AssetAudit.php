<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Đợt kiểm kê tài sản (bước 5.5): quét QR hoặc đánh dấu từng tài sản; đóng đợt thì tài sản chưa kiểm được báo thiếu. */
class AssetAudit extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const RESULTS = [
        'found' => ['Có mặt', 'success'],
        'damaged' => ['Có mặt, hư hỏng', 'warning'],
        'missing' => ['Không tìm thấy', 'danger'],
    ];

    protected $fillable = ['name', 'note'];

    protected $casts = ['closed_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(AssetAuditItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
