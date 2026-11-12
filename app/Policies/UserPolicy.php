<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Tài khoản quản trị nền tảng / chủ sở hữu tổ chức chỉ do chủ sở hữu (hoặc quản trị nền tảng — Gate::before) sửa.
     */
    public function update(User $user, User $target): bool
    {
        if ($target->isPlatformAdmin()) {
            return false;
        }

        // Tự sửa hồ sơ của mình, hoặc người có cấp THẤP HƠN (chủ sở hữu quản lý mọi cấp)
        return $user->can('users.update') && ($user->id === $target->id || $user->outranks($target));
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id && $user->can('users.delete') && ! $target->isProtectedAccount() && $user->outranks($target);
    }

    public function manage(User $user): bool
    {
        return $user->can('users.manage');
    }
}
