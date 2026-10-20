<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Hồ sơ nhân viên trong một tổ chức (1–1 với tư cách thành viên). Các trường nhạy cảm được mã hóa bằng APP_KEY
 * và chỉ hiển thị / sửa / xuất cho người có quyền "employees.view_sensitive"; nhật ký không ghi giá trị của chúng.
 */
class EmployeeProfile extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const STATUSES = [
        'probation' => ['Thử việc', 'info'],
        'active' => ['Đang làm việc', 'success'],
        'on_leave' => ['Nghỉ phép dài hạn', 'warning'],
        'resigned' => ['Đã nghỉ việc', 'secondary'],
    ];

    /** Trường nhạy cảm (mã hóa, quyền xem riêng). */
    public static function sensitiveFields(): array
    {
        return ['date_of_birth', 'personal_phone', 'personal_email', 'home_address', 'id_number', 'emergency_contact', 'hr_notes'];
    }

    protected $fillable = [
        'user_id', 'employee_code', 'job_title', 'manager_id', 'branch_id', 'hire_date', 'employment_status', 'work_phone',
        'date_of_birth', 'personal_phone', 'personal_email', 'home_address', 'id_number', 'emergency_contact', 'hr_notes',
    ];

    /** Ẩn khỏi JSON / toArray và khỏi nhật ký hoạt động (LogsActivity bỏ qua các trường $hidden). */
    protected $hidden = ['date_of_birth', 'personal_phone', 'personal_email', 'home_address', 'id_number', 'emergency_contact', 'hr_notes'];

    protected $casts = [
        'hire_date' => 'date',
        'date_of_birth' => 'encrypted', 'personal_phone' => 'encrypted', 'personal_email' => 'encrypted', 'home_address' => 'encrypted',
        'id_number' => 'encrypted', 'emergency_contact' => 'encrypted', 'hr_notes' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
