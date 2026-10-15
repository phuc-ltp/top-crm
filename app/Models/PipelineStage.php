<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
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
}
