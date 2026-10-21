<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kết quả kiểm kê một tài sản trong một đợt. Luôn truy cập qua AssetAudit (đã lọc theo tổ chức). */
class AssetAuditItem extends Model
{
    protected $fillable = ['asset_audit_id', 'asset_id', 'result', 'checked_by', 'checked_at', 'note'];

    protected $casts = ['checked_at' => 'datetime'];

    public function asset()
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
