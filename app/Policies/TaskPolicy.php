<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskPolicy extends CrmPolicy
{
    protected string $module = 'tasks';

    /** Thành viên dự án xem được công việc của dự án. */
    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) || ($user->can('tasks.view') && $model->inProjectOf($user));
    }

    /** Người quản lý dự án sửa được mọi công việc trong dự án của mình. */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model)
            || ($user->can('tasks.update') && $model->project_id && (int) $model->project?->manager_id === (int) $user->id);
    }
}