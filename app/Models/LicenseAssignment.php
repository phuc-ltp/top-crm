<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Lần gán giấy phép cho một người hoặc một thiết bị (bước 5.6). released_at trống = đang dùng một chỗ. */
class LicenseAssignment extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['software_license_id', 'user_id', 'asset_id', 'assigned_by', 'assigned_at', 'note'];

    protected $casts = ['assigned_at' => 'datetime', 'released_at' => 'datetime'];

    public function license()
    {
        return $this->belongsTo(SoftwareLicense::class, 'software_license_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
