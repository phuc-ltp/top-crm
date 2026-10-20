<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use BelongsToOrganization, HasCode, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'tickets';

    protected static string $codePrefix = 'TK';

    public const STATUSES = [
        'open' => ['Mới mở', 'info'],
        'in_progress' => ['Đang xử lý', 'primary'],
        'waiting' => ['Chờ phản hồi', 'warning'],
        'resolved' => ['Đã giải quyết', 'success'],
        'closed' => ['Đã đóng', 'secondary'],
    ];

    public const PRIORITIES = Task::PRIORITIES;

    protected $fillable = [
        'code', 'customer_id', 'contact_id', 'subject', 'description', 'priority', 'status', 'assigned_to',
        'created_by', 'resolved_at', 'closed_at', 'is_demo', 'category_id', 'satisfaction_rating', 'satisfaction_comment',
    ];

    protected $casts = [
        'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'is_demo' => 'boolean',
        'first_response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'first_responded_at' => 'datetime',
        'paused_at' => 'datetime', 'escalated_at' => 'datetime', 'paused_minutes' => 'integer', 'escalation_level' => 'integer',
        'satisfaction_rating' => 'integer',
    ];

    /**
     * SLA (bước 4.3): đặt hạn khi tạo; đổi mức ưu tiên thì tính lại hạn chưa đạt; đổi trạng thái thì tạm dừng / tiếp tục đồng hồ
     * và ghi lịch sử. Đặt ở model để mọi đường cập nhật (form, phản hồi, API) đều nhất quán.
     */
    protected static function booted(): void
    {
        static::creating(fn (SupportTicket $t) => app(\App\Services\SlaService::class)->applyDueDates($t));

        static::updating(function (SupportTicket $t) {
            $sla = app(\App\Services\SlaService::class);
            if ($t->isDirty('priority') && $t->first_response_due_at) {
                [$response, $resolve] = \App\Services\SlaService::policy()[$t->priority] ?? \App\Services\SlaService::DEFAULTS['medium'];
                $base = $t->created_at->copy()->addMinutes($t->paused_minutes);
                if (! $t->first_responded_at) {
                    $t->first_response_due_at = $base->copy()->addHours($response);
                }
                if (! $t->resolved_at) {
                    $t->resolution_due_at = $base->copy()->addHours($resolve);
                }
                $t->escalation_level = 0;
            }
            if ($t->isDirty('status')) {
                $sla->onStatusChange($t, $t->getOriginal('status'), $t->status);
            }
        });

        static::created(fn (SupportTicket $t) => $t->statusHistories()->create(['from_status' => null, 'to_status' => $t->status, 'user_id' => auth()->id()]));
        static::updated(function (SupportTicket $t) {
            if ($t->wasChanged('status')) {
                $t->statusHistories()->create(['from_status' => $t->getOriginal('status'), 'to_status' => $t->status, 'user_id' => auth()->id()]);
            }
        });
    }

    /** Ticket đang xử lý (không tính đang chờ khách) đã quá hạn xử lý, hoặc quá hạn phản hồi đầu mà chưa phản hồi. */
    public function scopeSlaBreached($query)
    {
        return $query->whereIn('status', ['open', 'in_progress'])
            ->where(fn ($w) => $w->where('resolution_due_at', '<', now())
                ->orWhere(fn ($r) => $r->whereNull('first_responded_at')->where('first_response_due_at', '<', now())));
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(TicketStatusHistory::class)->latest('id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /** @return array{response: string, resolution: string} */
    public function getSlaStateAttribute(): array
    {
        return \App\Services\SlaService::state($this);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }
}
