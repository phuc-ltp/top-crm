<?php

namespace App\Models;

use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasCode, HasOwnership, LogsActivity, SoftDeletes;

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
        'created_by', 'resolved_at', 'closed_at', 'is_demo',
    ];

    protected $casts = ['resolved_at' => 'datetime', 'closed_at' => 'datetime', 'is_demo' => 'boolean'];

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
