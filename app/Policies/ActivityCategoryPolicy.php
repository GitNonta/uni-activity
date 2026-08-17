<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ActivityCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update categories.
     */
    public function update(User $user, ActivityCategory $category): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete categories.
     */
    public function delete(User $user, ActivityCategory $category): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage system-wide required hours.
     */
    public function manageRequiredHours(User $user): bool
    {
        return $user->isAdmin();
    }
}
