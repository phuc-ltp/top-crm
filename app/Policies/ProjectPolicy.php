<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Dự án: thành viên được xem; sửa / xóa chỉ người quản lý, người tạo (hoặc người có "projects.view_all"). */
class ProjectPolicy extends CrmPolicy
{
    protected string $module = 'projects';

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) || ($user->can('projects.view') && $model->hasMember($user));
    }
}
