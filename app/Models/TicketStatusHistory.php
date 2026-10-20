<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lịch sử đổi trạng thái ticket (bất biến — chỉ thêm, không sửa). */
class TicketStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['support_ticket_id', 'from_status', 'to_status', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
