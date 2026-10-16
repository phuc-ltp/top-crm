<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasCode;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Nhà cung cấp (theo tổ chức). */
class Supplier extends Model
{
    use BelongsToOrganization, HasCode, LogsActivity, SoftDeletes;

    protected static string $codePrefix = 'NCC';

    public const STATUSES = [
        'active' => ['Đang hợp tác', 'success'],
        'inactive' => ['Ngừng hợp tác', 'secondary'],
    ];

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'address', 'tax_code', 'website', 'notes', 'status', 'created_by', 'is_demo',
    ];

    protected $casts = ['is_demo' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
