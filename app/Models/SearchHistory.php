<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Từ khóa tìm gần đây của một người dùng trong một tổ chức (riêng tư — chỉ chính người đó xem).
 * Truy vấn luôn kèm organization_id + user_id rõ ràng.
 */
class SearchHistory extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'query'];
}
