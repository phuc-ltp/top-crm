<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'slug', 'position', 'probability', 'color', 'is_won', 'is_lost'];

    protected $casts = ['is_won' => 'boolean', 'is_lost' => 'boolean'];

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
