<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    use BelongsToOrganization, \App\Models\Concerns\LogsActivity;

    protected $fillable = ['pipeline_id', 'name', 'slug', 'position', 'probability', 'color', 'is_won', 'is_lost'];

    protected $casts = ['is_won' => 'boolean', 'is_lost' => 'boolean'];

    protected static function booted(): void
    {
        // Giai đoạn không chỉ định pipeline → thuộc pipeline mặc định của tổ chức (tạo nếu chưa có)
        static::creating(function (PipelineStage $stage) {
            if (! $stage->pipeline_id) {
                $stage->pipeline_id = (Pipeline::where('is_default', true)->first()
                    ?? Pipeline::create(['name' => 'Pipeline bán hàng', 'is_default' => true, 'position' => 1]))->id;
            }
        });
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    public function isClosed(): bool
    {
        return $this->is_won || $this->is_lost;
    }

    /** Tên hiển thị theo ngôn ngữ giao diện (tên mặc định của hệ thống có bản dịch; tên tự đặt giữ nguyên). */
    public function getLabelAttribute(): string
    {
        return __($this->name);
    }
}
