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

    /** Chỉ Super Admin được sửa tài khoản Super Admin (Gate::before đã cho phép Super Admin). */
    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && ! $target->hasRole('Super Admin');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id && $user->can('users.delete') && ! $target->hasRole('Super Admin');
    }

    public function manage(User $user): bool
    {
        return $user->can('users.manage');
    }
}
