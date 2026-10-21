<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Bản ghi công một ngày của một người (bước 5.2). Vị trí và ảnh selfie là dữ liệu cá nhân nhạy cảm:
 * ẩn khỏi JSON, ảnh lưu ở storage/app/private và chỉ xem qua controller có kiểm tra quyền.
 */
class AttendanceRecord extends Model
{
    use BelongsToOrganization;

    public const STATUSES = [
        'present' => ['Đủ công', 'success'],
        'late' => ['Đi trễ / về sớm', 'warning'],
        'incomplete' => ['Chưa chấm ra', 'info'],
        'absent' => ['Vắng', 'secondary'],
    ];

    protected $fillable = ['user_id', 'work_date'];

    protected $hidden = ['check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng', 'check_in_selfie', 'check_out_selfie', 'check_in_ip', 'check_out_ip'];

    protected $casts = [
        'work_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'adjusted_at' => 'datetime',
        'check_in_within' => 'boolean', 'check_out_within' => 'boolean',
        'worked_minutes' => 'integer', 'late_minutes' => 'integer', 'early_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (AttendanceRecord $r) {
            foreach (['check_in_selfie', 'check_out_selfie'] as $f) {
                if ($r->{$f}) {
                    Storage::disk('local')->delete($r->{$f});
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkInLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'check_in_location_id');
    }

    public function checkOutLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'check_out_location_id');
    }

    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /** Bản ghi cần quản lý xem xét: ngoài vùng văn phòng. */
    public function getNeedsReviewAttribute(): bool
    {
        return $this->check_in_within === false || $this->check_out_within === false;
    }
}
