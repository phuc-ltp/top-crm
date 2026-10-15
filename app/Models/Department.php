<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'code', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
