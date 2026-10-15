<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['user_id', 'year', 'month', 'target_amount'];

    protected $casts = ['target_amount' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
