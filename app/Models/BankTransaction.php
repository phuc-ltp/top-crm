<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Giao dịch ngân hàng nhận tự động (SePay) — chỉ hệ thống tạo, người dùng chỉ xem / ghép hóa đơn. */
class BankTransaction extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['amount' => 'decimal:2', 'transaction_at' => 'datetime'];

    public static function statuses(): array
    {
        return [
            'matched' => ['Đã ghi nhận vào hóa đơn', 'success'],
            'unmatched' => ['Chưa khớp — cần xử lý', 'warning'],
            'ignored' => ['Bỏ qua (tiền ra)', 'secondary'],
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
