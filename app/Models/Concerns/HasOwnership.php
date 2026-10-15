<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Giới hạn dữ liệu theo phạm vi quyền: người dùng không có quyền "{module}.view_all"
 * chỉ thấy bản ghi mình phụ trách hoặc tự tạo.
 *
 * Model sử dụng cần khai báo: protected static string $permissionModule = 'customers';
 */
trait HasOwnership
{
    public static function permissionModule(): string
    {
        return static::$permissionModule;
    }

    /** Các cột xác định "chủ sở hữu" bản ghi. */
    public function ownerColumns(): array
    {
        return property_exists($this, 'ownerColumns') ? $this->ownerColumns : ['assigned_to', 'created_by'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(static::permissionModule().'.view_all')) {
            return $query;
        }

        $columns = $this->ownerColumns();

        return $query->where(function (Builder $q) use ($columns, $user) {
            foreach ($columns as $column) {
                $q->orWhere($this->qualifyColumn($column), $user->id);
            }
        });
    }

    public function isOwnedBy(User $user): bool
    {
        foreach ($this->ownerColumns() as $column) {
            if ((int) $this->getAttribute($column) === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
