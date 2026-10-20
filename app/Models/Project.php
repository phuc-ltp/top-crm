<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dự án: nhóm công việc có người quản lý, thành viên, thời gian và tiến độ.
 * Phạm vi xem: người có "projects.view_all", hoặc người quản lý / người tạo / thành viên dự án.
 * Sửa / xóa: chỉ người quản lý, người tạo (hoặc người có "projects.view_all").
 */
class Project extends Model
{
    use BelongsToOrganization, HasCode, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'projects';

    protected static string $codePrefix = 'DA';

    protected array $ownerColumns = ['manager_id', 'created_by'];

    public const STATUSES = [
        'planning' => ['Lên kế hoạch', 'secondary'],
        'active' => ['Đang thực hiện', 'primary'],
        'on_hold' => ['Tạm dừng', 'warning'],
        'completed' => ['Hoàn thành', 'success'],
        'cancelled' => ['Đã hủy', 'dark'],
    ];

    protected $fillable = [
        'name', 'description', 'customer_id', 'manager_id', 'status', 'priority', 'start_date', 'end_date', 'budget', 'created_by', 'is_demo',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'budget' => 'decimal:2', 'is_demo' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('projects.view_all')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where($this->qualifyColumn('manager_id'), $user->id)
            ->orWhere($this->qualifyColumn('created_by'), $user->id)
            ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id)));
    }

    public function hasMember(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->members()->where('users.id', $user->id)->exists();
    }

    /** Tiến độ (%) = việc hoàn thành / việc không bị hủy. Dùng số đếm có sẵn (withCount) nếu đã nạp. */
    public function getProgressAttribute(): int
    {
        $total = $this->tasks_active_count ?? $this->tasks()->where('status', '!=', 'cancelled')->count();
        $done = $this->tasks_done_count ?? $this->tasks()->where('status', 'completed')->count();

        return $total > 0 ? (int) floor($done * 100 / $total) : 0;
    }

    public function getIsLateAttribute(): bool
    {
        return in_array($this->status, ['planning', 'active', 'on_hold'], true) && $this->end_date && $this->end_date->lt(today());
    }

    public function scopeWithProgress(Builder $query): Builder
    {
        return $query->withCount([
            'tasks as tasks_active_count' => fn ($q) => $q->where('status', '!=', 'cancelled'),
            'tasks as tasks_done_count' => fn ($q) => $q->where('status', 'completed'),
            'tasks as tasks_late_count' => fn ($q) => $q->open()->where('due_date', '<', now()),
        ]);
    }
}
