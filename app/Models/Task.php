<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToOrganization, HasOwnership, LogsActivity, SoftDeletes;

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
        'project_id', 'parent_id', 'start_date', 'estimated_hours',
    ];

    protected $casts = [
        'due_date' => 'datetime', 'completed_at' => 'datetime', 'reminded_at' => 'datetime', 'is_demo' => 'boolean',
        'start_date' => 'date', 'estimated_hours' => 'decimal:2',
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

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('due_date')->orderBy('id');
    }

    /** Các việc phải xong trước việc này. */
    public function dependencies()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_id')->withTimestamps();
    }

    /** Các việc đang chờ việc này xong. */
    public function dependents()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_id', 'task_id')->withTimestamps();
    }

    /** Việc chặn chưa xong (chưa hoàn thành và chưa hủy). */
    public function openBlockers()
    {
        return $this->dependencies()->whereNotIn('status', ['completed', 'cancelled']); // không ghi rõ bảng: đúng cả khi đếm (withCount dùng bí danh bảng)
    }

    /**
     * Phạm vi xem: người có "tasks.view_all"; người phụ trách / người tạo;
     * hoặc thành viên (kể cả người quản lý, người tạo) của dự án chứa công việc.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('tasks.view_all')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where($this->qualifyColumn('assigned_to'), $user->id)
            ->orWhere($this->qualifyColumn('created_by'), $user->id)
            ->orWhereIn($this->qualifyColumn('project_id'), self::memberProjectIds($user)));
    }

    /** Truy vấn con: id các dự án người dùng là quản lý / người tạo / thành viên. */
    public static function memberProjectIds(User $user)
    {
        return Project::query()->select('projects.id')->where(fn ($q) => $q->where('manager_id', $user->id)->orWhere('created_by', $user->id)
            ->orWhereIn('projects.id', \Illuminate\Support\Facades\DB::table('project_members')->select('project_id')->where('user_id', $user->id)));
    }

    public function inProjectOf(User $user): bool
    {
        return $this->project_id && self::memberProjectIds($user)->whereKey($this->project_id)->exists();
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
