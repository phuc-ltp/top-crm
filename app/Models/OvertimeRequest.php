<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Đơn đề nghị tăng ca (bước 5.3). */
class OvertimeRequest extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const STATUSES = [
        'pending' => ['Chờ duyệt', 'warning'],
        'approved' => ['Đã duyệt', 'success'],
        'rejected' => ['Từ chối', 'danger'],
        'cancelled' => ['Đã hủy', 'secondary'],
    ];

    public const LEVELS = [
        'high' => ['Cao', 'danger'],
        'medium' => ['Vừa', 'warning'],
        'low' => ['Thấp', 'info'],
    ];

    protected $fillable = ['user_id', 'work_date', 'start_time', 'end_time', 'hours', 'level', 'reason', 'status'];

    protected $casts = ['work_date' => 'date', 'hours' => 'decimal:2', 'rate' => 'decimal:2', 'decided_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** Thành tiền (giờ × đơn giá chốt) — chỉ có khi đã duyệt. */
    public function getAmountAttribute(): ?string
    {
        return $this->rate !== null ? \App\Support\Decimal::round(\App\Support\Decimal::mul($this->hours, $this->rate), 0) : null;
    }
}
