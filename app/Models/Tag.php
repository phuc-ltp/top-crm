<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'color'];

    public function customers()
    {
        return $this->belongsToMany(Customer::class);
    }
}
