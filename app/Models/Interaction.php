<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Hoạt động gắn với khách hàng / người liên hệ / lead / cơ hội (bảng interactions).
 * - status "done": đã diễn ra (ghi nhận lại cuộc gọi, ghi chú...), thời điểm = occurred_at.
 * - status "planned": việc sắp làm cho người phụ trách (assigned_to) vào lúc scheduled_at; xong thì ghi kết quả.
 */
class Interaction extends Model
{
    use BelongsToOrganization;

    public const TYPES = [
        'note' => ['Ghi chú', 'bi-sticky'],
        'call' => ['Cuộc gọi', 'bi-telephone'],
        'email' => ['Email', 'bi-envelope'],
        'meeting' => ['Cuộc họp', 'bi-people'],
        'follow_up' => ['Việc theo dõi', 'bi-flag'],
        'other' => ['Khác', 'bi-three-dots'],
    ];

    public const STATUSES = [
        'planned' => ['Đã lên lịch', 'info'],
        'done' => ['Đã hoàn thành', 'success'],
    ];

    /** Đối tượng có thể gắn hoạt động: tên morph → [class, module quyền]. */
    public const SUBJECTS = [
        'customer' => [Customer::class, 'customers'],
        'contact' => [Contact::class, 'contacts'],
        'lead' => [Lead::class, 'leads'],
        'opportunity' => [Opportunity::class, 'opportunities'],
    ];

    protected $fillable = ['type', 'status', 'subject', 'content', 'outcome', 'occurred_at', 'scheduled_at', 'completed_at', 'user_id', 'assigned_to'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function interactable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Tên morph của đối tượng (customer / contact / lead / opportunity). */
    public function subjectType(): ?string
    {
        return $this->interactable_type ?: null;
    }

    /** Trang chi tiết của đối tượng gắn hoạt động. */
    public function subjectUrl(): ?string
    {
        $routes = ['customer' => 'customers.show', 'contact' => 'contacts.show', 'lead' => 'leads.show', 'opportunity' => 'opportunities.show'];
        $route = $routes[$this->interactable_type] ?? null;

        return $route && $this->interactable_id ? route($route, $this->interactable_id) : null;
    }

    /** Tên hiển thị của đối tượng. */
    public function subjectLabel(): string
    {
        $s = $this->interactable;

        return (string) ($s?->name ?? $s?->full_name ?? $s?->title ?? '—');
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isOverdue(): bool
    {
        return $this->isPlanned() && $this->scheduled_at !== null && $this->scheduled_at->isPast();
    }

    /** Thời điểm hiển thị trên dòng thời gian. */
    public function moment(): ?\Illuminate\Support\Carbon
    {
        return $this->isPlanned() ? $this->scheduled_at : ($this->occurred_at ?? $this->completed_at ?? $this->created_at);
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', 'planned');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->planned()->where('scheduled_at', '<', now());
    }

    /** Hoạt động do $user phụ trách (được giao; hoặc tự tạo mà không giao cho ai). */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere(fn ($w) => $w->whereNull('assigned_to')->where('user_id', $user->id)));
    }

    /**
     * Hoạt động $user được xem: do mình phụ trách/tạo, hoặc gắn với đối tượng mình được xem
     * (theo quyền module + phạm vi "chỉ của mình" của từng loại đối tượng).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $classes = collect(self::SUBJECTS)->filter(fn ($def) => $user->can($def[1].'.view'))->map(fn ($def) => $def[0])->values()->all();

        return $query->where(function ($q) use ($user, $classes) {
            $q->where('assigned_to', $user->id)->orWhere('user_id', $user->id);
            if ($classes) {
                $q->orWhereHasMorph('interactable', $classes, fn ($sub) => $sub->visibleTo($user));
            }
        });
    }

    /** Người dùng được sửa / xóa hoạt động (người tạo, chủ sở hữu tổ chức, quản trị nền tảng). */
    public function editableBy(User $user): bool
    {
        return $this->user_id === $user->id || $user->isPlatformAdmin() || $user->isOrganizationOwner();
    }

    /** Người dùng được đánh dấu hoàn thành (người phụ trách, người tạo, hoặc người được sửa đối tượng). */
    public function completableBy(User $user): bool
    {
        return $this->assigned_to === $user->id || $this->editableBy($user)
            || ($this->interactable && $user->can('update', $this->interactable));
    }
}
