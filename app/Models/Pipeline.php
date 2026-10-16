<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Pipeline bán hàng của một tổ chức (VD: Bán hàng, Dự án, Bán lẻ). Mỗi tổ chức có đúng một pipeline mặc định.
 * Mỗi pipeline có các giai đoạn riêng; cơ hội chỉ di chuyển giữa các giai đoạn trong pipeline của nó.
 */
class Pipeline extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const MAX_PER_ORGANIZATION = 10;

    protected $fillable = ['name', 'is_default', 'is_active', 'position'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position')->orderBy('id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_default')->orderBy('position')->orderBy('name');
    }

    /** Pipeline mặc định của tổ chức đang làm việc. */
    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::ordered()->first();
    }

    /** Giai đoạn mở đầu tiên (dùng khi tạo cơ hội mới / chuyển đổi lead). */
    public function firstOpenStage(): ?PipelineStage
    {
        return $this->stages()->where('is_won', false)->where('is_lost', false)->first();
    }

    public function getLabelAttribute(): string
    {
        return __($this->name);
    }
}
