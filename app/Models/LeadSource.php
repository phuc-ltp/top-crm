<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class LeadSource extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /** Tên hiển thị theo ngôn ngữ giao diện (tên mặc định của hệ thống có bản dịch; tên tự đặt giữ nguyên). */
    public function getLabelAttribute(): string
    {
        return __($this->name);
    }
}
