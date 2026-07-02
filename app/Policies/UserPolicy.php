<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrAbove();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdminOrAbove();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrAbove();
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->isAdminOrAbove()) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        if (! $user->isAdminOrAbove()) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return true;
    }
}
