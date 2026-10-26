<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Khoản chi thực tế của chiến dịch marketing. */
class CampaignExpense extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const CATEGORIES = [
        'ads' => ['Quảng cáo trả phí', 'primary'],
        'content' => ['Sản xuất nội dung', 'info'],
        'event' => ['Sự kiện / hội thảo', 'warning'],
        'tool' => ['Công cụ / phần mềm', 'secondary'],
        'staff' => ['Nhân sự / cộng tác viên', 'success'],
        'other' => ['Khác', 'secondary'],
    ];

    protected $fillable = ['campaign_id', 'spent_on', 'category', 'amount', 'note', 'created_by'];

    protected $casts = ['spent_on' => 'date', 'amount' => 'decimal:2'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
