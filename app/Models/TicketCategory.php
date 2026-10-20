<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/** Danh mục ticket hỗ trợ (bước 4.3). */
class TicketCategory extends Model
{
    use BelongsToOrganization, LogsActivity;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'category_id');
    }
}
