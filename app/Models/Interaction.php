<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    public const TYPES = [
        'note' => ['Ghi chú', 'bi-sticky'],
        'call' => ['Cuộc gọi', 'bi-telephone'],
        'email' => ['Email', 'bi-envelope'],
        'meeting' => ['Cuộc họp', 'bi-people'],
        'other' => ['Khác', 'bi-three-dots'],
    ];

    protected $fillable = ['type', 'subject', 'content', 'occurred_at', 'user_id'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function interactable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
