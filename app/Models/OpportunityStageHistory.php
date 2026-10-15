<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpportunityStageHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['opportunity_id', 'from_stage_id', 'to_stage_id', 'changed_by', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function fromStage()
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
