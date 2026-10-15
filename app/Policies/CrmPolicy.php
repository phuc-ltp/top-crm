<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy chung: kiểm tra quyền hành động "{module}.{action}" và phạm vi dữ liệu.
 * Người không có "{module}.view_all" chỉ thao tác được trên bản ghi mình phụ trách/tạo.
 */
abstract class CrmPolicy
{
    protected string $module;

    /** Model có khái niệm "chủ sở hữu" hay không (sản phẩm thì không). */
    protected bool $ownership = true;

    protected function allows(User $user, string $action, ?Model $model = null): bool
    {
        if (! $user->can("{$this->module}.{$action}")) {
            return false;
        }
        if (! $model || ! $this->ownership || $user->can("{$this->module}.view_all")) {
            return true;
        }

        return method_exists($model, 'isOwnedBy') && $model->isOwnedBy($user);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'update', $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete', $model);
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    public function assign(User $user): bool
    {
        return $user->can("{$this->module}.assign");
    }

    public function approve(User $user): bool
    {
        return $user->can("{$this->module}.approve");
    }
}
