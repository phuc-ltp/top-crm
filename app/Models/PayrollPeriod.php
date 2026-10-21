<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Kỳ lương tháng (bước 5.4). Đã duyệt / đã trả thì khóa, không tính lại hay sửa phiếu. */
class PayrollPeriod extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const STATUSES = [
        'draft' => ['Nháp', 'secondary'],
        'submitted' => ['Chờ duyệt', 'warning'],
        'approved' => ['Đã duyệt', 'info'],
        'paid' => ['Đã trả', 'success'],
    ];

    protected $fillable = ['period', 'status', 'note'];

    protected $casts = ['period' => 'date', 'calculated_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'paid'], true);
    }

    /** Nhân viên chỉ thấy phiếu lương khi kỳ đã duyệt. */
    public function isPublished(): bool
    {
        return $this->isLocked();
    }

    public function getLabelAttribute(): string
    {
        return __('Tháng :m/:y', ['m' => $this->period->month, 'y' => $this->period->year]);
    }
}
