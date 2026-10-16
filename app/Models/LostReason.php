<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Lý do mất lead / cơ hội do tổ chức tự cấu hình. */
class LostReason extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const DEFAULTS = ['Giá cao so với ngân sách', 'Chọn đối thủ cạnh tranh', 'Không còn nhu cầu', 'Không liên lạc được', 'Thông tin không chính xác', 'Khác'];

    public const APPLIES = ['both' => 'Lead và cơ hội', 'lead' => 'Chỉ lead', 'opportunity' => 'Chỉ cơ hội'];

    protected $fillable = ['name', 'applies_to', 'is_active', 'position'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeForLeads(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereIn('applies_to', ['both', 'lead'])->orderBy('position')->orderBy('name');
    }
}
