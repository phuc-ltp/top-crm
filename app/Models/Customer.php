<?php

namespace App\Models;

use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasCode, HasFactory, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'customers';

    protected static string $codePrefix = 'KH';

    public const TYPES = ['individual' => 'Cá nhân', 'business' => 'Doanh nghiệp'];

    public const STATUSES = [
        'active' => ['Đang giao dịch', 'success'],
        'potential' => ['Tiềm năng', 'info'],
        'vip' => ['VIP', 'orange'],
        'inactive' => ['Ngừng giao dịch', 'secondary'],
    ];

    protected $fillable = [
        'code', 'name', 'phone', 'email', 'address', 'company', 'type', 'lead_source_id', 'status',
        'assigned_to', 'created_by', 'updated_by', 'notes', 'is_demo',
    ];

    protected $casts = ['is_demo' => 'boolean'];

    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable')->latest('occurred_at');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'related');
    }
}
