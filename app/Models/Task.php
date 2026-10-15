<?php

namespace App\Models;

use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'tasks';

    public const STATUSES = [
        'pending' => ['Chờ xử lý', 'secondary'],
        'in_progress' => ['Đang thực hiện', 'primary'],
        'completed' => ['Hoàn thành', 'success'],
        'overdue' => ['Quá hạn', 'danger'],
        'cancelled' => ['Đã hủy', 'dark'],
    ];

    public const PRIORITIES = [
        'low' => ['Thấp', 'secondary'],
        'medium' => ['Trung bình', 'info'],
        'high' => ['Cao', 'warning'],
        'urgent' => ['Khẩn cấp', 'danger'],
    ];

    /** Các loại đối tượng có thể gắn công việc. */
    public const RELATED_TYPES = [
        'customer' => 'Khách hàng', 'lead' => 'Lead', 'opportunity' => 'Cơ hội',
    ];

    protected $fillable = [
        'title', 'description', 'priority', 'status', 'due_date', 'completed_at', 'reminded_at',
        'related_type', 'related_id', 'assigned_to', 'created_by', 'is_demo',
    ];

    protected $casts = [
        'due_date' => 'datetime', 'completed_at' => 'datetime', 'reminded_at' => 'datetime', 'is_demo' => 'boolean',
    ];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function checklist()
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /** Công việc chưa xong (chờ xử lý, đang làm, quá hạn). */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress', 'overdue']);
    }

    public function getIsLateAttribute(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'overdue'], true)
            && $this->due_date && $this->due_date->isPast();
    }
}
