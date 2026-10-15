<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToOrganization, LogsActivity;

    public const METHODS = [
        'cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'card' => 'Thẻ ngân hàng', 'other' => 'Khác',
    ];

    protected $fillable = ['invoice_id', 'amount', 'payment_date', 'method', 'reference', 'notes', 'recorded_by', 'is_demo'];

    protected $casts = ['amount' => 'decimal:2', 'payment_date' => 'date', 'is_demo' => 'boolean'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
