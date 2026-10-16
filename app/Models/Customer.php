<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToOrganization, HasCode, HasFactory, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'customers';

    protected static string $codePrefix = 'KH';

    public const TYPES = ['individual' => 'Cá nhân', 'business' => 'Doanh nghiệp'];

    public const STATUSES = [
        'active' => ['Đang giao dịch', 'success'],
        'potential' => ['Tiềm năng', 'info'],
        'vip' => ['VIP', 'orange'],
        'inactive' => ['Ngừng giao dịch', 'secondary'],
    ];

    /** Phân khúc khách hàng. */
    public const SEGMENTS = [
        'retail' => ['Khách lẻ', 'secondary'],
        'sme' => ['Doanh nghiệp vừa & nhỏ', 'info'],
        'enterprise' => ['Doanh nghiệp lớn', 'purple'],
        'partner' => ['Đối tác / đại lý', 'orange'],
        'government' => ['Cơ quan nhà nước', 'success'],
    ];

    /** Giai đoạn vòng đời (thời điểm đổi gần nhất: lifecycle_changed_at; lịch sử trong nhật ký hoạt động). */
    public const LIFECYCLE_STAGES = [
        'prospect' => ['Tiềm năng', 'info'],
        'new' => ['Khách hàng mới', 'primary'],
        'active' => ['Đang giao dịch', 'success'],
        'loyal' => ['Trung thành', 'orange'],
        'at_risk' => ['Có nguy cơ rời bỏ', 'warning'],
        'churned' => ['Đã rời bỏ', 'secondary'],
    ];

    /** Kênh liên lạc ưa thích. */
    public const CHANNELS = [
        'phone' => 'Điện thoại',
        'email' => 'Email',
        'sms' => 'Tin nhắn SMS',
        'zalo' => 'Zalo',
        'in_person' => 'Gặp trực tiếp',
    ];

    protected $fillable = [
        'code', 'name', 'phone', 'email', 'address', 'country', 'company', 'type', 'lead_source_id', 'status',
        'segment', 'lifecycle_stage', 'preferred_channel', 'marketing_consent', 'marketing_consent_source', 'custom_fields',
        'assigned_to', 'created_by', 'updated_by', 'notes', 'is_demo',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
        'marketing_consent' => 'boolean',
        'marketing_consent_at' => 'datetime',
        'lifecycle_changed_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $c) {
            // Ghi thời điểm khi đổi giai đoạn vòng đời / thay đổi đồng ý nhận marketing (do hệ thống ghi, không nhập tay)
            if ($c->isDirty('lifecycle_stage')) {
                $c->lifecycle_changed_at = $c->lifecycle_stage ? now() : null;
            }
            if ($c->isDirty('marketing_consent') && ($c->exists || $c->marketing_consent)) {
                $c->marketing_consent_at = now();
            }
        });
    }

    /** Khách hàng đã được gộp vào khách hàng khác (bản ghi gốc được lưu trữ). */
    public function mergedInto()
    {
        return $this->belongsTo(Customer::class, 'merged_into_id')->withTrashed();
    }

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

    /** Tệp đính kèm (hợp đồng, giấy tờ...) — lưu ngoài thư mục public, tải qua controller có kiểm tra quyền. */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
