<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Tài sản (bước 5.5). Trạng thái và người giữ chỉ đổi qua AssetService để luôn có nhật ký luân chuyển. */
class Asset extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    public const STATUSES = [
        'available' => ['Sẵn sàng', 'success'],
        'in_use' => ['Đang sử dụng', 'primary'],
        'borrowed' => ['Đang cho mượn', 'info'],
        'maintenance' => ['Đang bảo trì', 'warning'],
        'broken' => ['Hỏng', 'danger'],
        'lost' => ['Thất lạc', 'danger'],
        'disposed' => ['Đã thanh lý', 'secondary'],
    ];

    protected $fillable = [
        'code', 'name', 'asset_category_id', 'serial_number', 'model', 'supplier_id', 'branch_id', 'location',
        'purchase_date', 'purchase_cost', 'salvage_value', 'depreciation_months', 'warranty_until', 'description',
    ];

    protected $casts = [
        'purchase_date' => 'date', 'warranty_until' => 'date', 'due_back_at' => 'date', 'disposed_at' => 'datetime',
        'purchase_cost' => 'decimal:2', 'salvage_value' => 'decimal:2', 'disposal_value' => 'decimal:2',
    ];

    protected $hidden = ['qr_token'];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            $asset->qr_token ??= Str::random(32);
        });
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function holder()
    {
        return $this->belongsTo(User::class, 'holder_id');
    }

    public function events()
    {
        return $this->hasMany(AssetEvent::class)->latest('occurred_at')->latest('id');
    }

    public function isDisposed(): bool
    {
        return $this->status === 'disposed';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'borrowed' && $this->due_back_at && $this->due_back_at->lt(today());
    }
}
