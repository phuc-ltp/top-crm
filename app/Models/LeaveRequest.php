<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Đơn nghỉ phép. */
class LeaveRequest extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const TYPES = [
        'annual' => ['Phép năm', 'primary'],
        'personal' => ['Việc riêng có lương (cưới, tang)', 'info'],
        'sick' => ['Nghỉ ốm (BHXH chi trả)', 'warning'],
        'unpaid' => ['Nghỉ không lương', 'secondary'],
    ];

    /** Loại nghỉ được trả lương (phép năm trừ quỹ; việc riêng theo Điều 115 không trừ quỹ). */
    public static function paidTypes(): array
    {
        return ['annual', 'personal'];
    }

    public const PARTS = [
        'full' => ['Cả ngày', 'secondary'],
        'am' => ['Buổi sáng', 'secondary'],
        'pm' => ['Buổi chiều', 'secondary'],
    ];

    public const STATUSES = [
        'pending' => ['Chờ duyệt', 'warning'],
        'approved' => ['Đã duyệt', 'success'],
        'rejected' => ['Từ chối', 'danger'],
        'cancelled' => ['Đã hủy', 'secondary'],
    ];

    protected $fillable = ['user_id', 'type', 'start_date', 'end_date', 'part', 'days', 'reason', 'status'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'days' => 'decimal:1', 'decided_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isPaid(): bool
    {
        return in_array($this->type, self::paidTypes(), true);
    }
}
