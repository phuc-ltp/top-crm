<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một cài đặt của một tổ chức. Luôn truy vấn kèm organization_id rõ ràng (qua App\Models\Setting),
 * không dùng global scope để Setting đọc được trước khi middleware xác định tổ chức.
 */
class OrganizationSetting extends Model
{
    protected $fillable = ['organization_id', 'key', 'value'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
