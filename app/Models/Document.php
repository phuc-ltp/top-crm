<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tài liệu trong thư viện (bước 4.5), có nhiều phiên bản tệp.
 * Phạm vi xem: private (chỉ người tải lên), team (thành viên nhóm được chọn), organization (mọi người có quyền xem tài liệu).
 * Người có "documents.manage" xem / quản trị mọi tài liệu của tổ chức.
 */
class Document extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    public const CATEGORIES = [
        'contract' => ['Hợp đồng', 'bi-file-earmark-text'],
        'proposal' => ['Báo giá / đề xuất', 'bi-file-earmark-bar-graph'],
        'finance' => ['Tài chính / hóa đơn', 'bi-receipt'],
        'policy' => ['Quy định / chính sách', 'bi-journal-bookmark'],
        'technical' => ['Kỹ thuật', 'bi-gear'],
        'marketing' => ['Marketing', 'bi-megaphone'],
        'other' => ['Khác', 'bi-file-earmark'],
    ];

    public const VISIBILITIES = [
        'organization' => ['Toàn công ty', 'success'],
        'team' => ['Theo nhóm', 'info'],
        'private' => ['Riêng tư', 'secondary'],
    ];

    /** Đối tượng có thể liên kết: tên → [class, route chi tiết]. */
    public static function relatedTypes(): array
    {
        return [
            'customer' => [Customer::class, 'customers.show'],
            'opportunity' => [Opportunity::class, 'opportunities.show'],
            'project' => [Project::class, 'projects.show'],
        ];
    }

    /** Loại tệp xem trước trực tiếp trên trình duyệt (an toàn để hiển thị inline). */
    public static function previewableMimes(): array
    {
        return ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    }

    protected $fillable = ['title', 'description', 'category', 'visibility', 'team_id', 'related_type', 'related_id', 'created_by'];

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function latestVersion()
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('documents.manage')) {
            return $query;
        }
        if (! $user->can('documents.view')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q->where('created_by', $user->id)
            ->orWhere('visibility', 'organization')
            ->orWhere(fn ($t) => $t->where('visibility', 'team')
                ->whereIn('team_id', \Illuminate\Support\Facades\DB::table('team_members')->select('team_id')->where('user_id', $user->id))));
    }

    public function viewableBy(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    /** Sửa, tải phiên bản mới, xóa: người tải lên hoặc người quản trị tài liệu. */
    public function manageableBy(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id || $user->can('documents.manage');
    }

    public function relatedUrl(): ?string
    {
        $route = self::relatedTypes()[$this->related_type][1] ?? null;

        return $route && $this->related_id ? route($route, $this->related_id) : null;
    }
}
