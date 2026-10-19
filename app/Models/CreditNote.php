<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Phiếu giảm trừ (GT) cho một hóa đơn đã phát hành — chứng từ nội bộ, bất biến. Giảm số còn phải thu. */
class CreditNote extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['number', 'invoice_id', 'issue_date', 'amount', 'reason', 'created_by', 'is_demo'];

    protected $casts = ['issue_date' => 'date', 'amount' => 'decimal:2', 'is_demo' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn (CreditNote $c) => $c->number ??= 'TMP-'.\Illuminate\Support\Str::random(20));
        static::created(function (CreditNote $c) {
            $c->number = 'GT'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT);
            $c->saveQuietly();
        });
        static::updating(function (CreditNote $c) {
            if (array_diff(array_keys($c->getDirty()), ['number', 'updated_at'])) {
                throw new \LogicException('Phiếu giảm trừ không được sửa.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Phiếu giảm trừ không được xóa.'));
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
