<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\HasOwnership;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use BelongsToOrganization, HasCode, HasOwnership, LogsActivity, SoftDeletes;

    protected static string $permissionModule = 'opportunities';

    protected static string $codePrefix = 'CH';

    protected $fillable = [
        'code', 'title', 'customer_id', 'contact_id', 'lead_id', 'pipeline_id', 'pipeline_stage_id', 'value', 'probability',
        'expected_close_date', 'closed_at', 'lost_reason', 'lost_reason_id', 'position', 'assigned_to', 'created_by', 'notes', 'is_demo',
    ];

    protected static function booted(): void
    {
        // pipeline_id luôn khớp với pipeline của giai đoạn (một nguồn sự thật, mọi đường lưu đều đi qua đây)
        static::saving(function (Opportunity $o) {
            if ($o->pipeline_stage_id && ($o->isDirty('pipeline_stage_id') || ! $o->pipeline_id)) {
                $o->pipeline_id = PipelineStage::whereKey($o->pipeline_stage_id)->value('pipeline_id');
            }
        });
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** Sản phẩm / dịch vụ trong cơ hội. */
    public function items()
    {
        return $this->hasMany(OpportunityItem::class)->orderBy('position')->orderBy('id');
    }

    public function lostReasonRef()
    {
        return $this->belongsTo(LostReason::class, 'lost_reason_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    protected $casts = [
        'value' => 'decimal:2', 'expected_close_date' => 'date', 'closed_at' => 'datetime', 'is_demo' => 'boolean',
    ];

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stageHistories()
    {
        return $this->hasMany(OpportunityStageHistory::class)->latest('created_at');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable')->latest('occurred_at');
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'related');
    }

    /** Giá trị có trọng số = giá trị × xác suất. */
    public function getWeightedValueAttribute(): float
    {
        return round((float) $this->value * $this->probability / 100, 2);
    }

    public function scopeOpen($query)
    {
        return $query->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false));
    }
}
