<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any activities.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can view the specific activity.
     */
    public function view(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isStaff()) {
            return false;
        }

        // Own activity or same faculty scoped activity
        return $activity->created_by === $user->id
            || ($user->faculty !== null && $activity->faculty === $user->faculty);
    }

    /**
     * Determine whether the user can create activities.
     */
    public function create(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can update the activity.
     */
    public function update(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isStaff()) {
            return false;
        }

        return $activity->created_by === $user->id
            || ($user->faculty !== null && $activity->faculty === $user->faculty);
    }

    /**
     * Determine whether the user can delete the activity.
     */
    public function delete(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isStaff()) {
            return false;
        }

        return $activity->created_by === $user->id
            || ($user->faculty !== null && $activity->faculty === $user->faculty);
    }

    /**
     * Determine whether the user can manage settings, QR codes, or monitor the activity.
     */
    public function manage(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isStaff()) {
            return false;
        }

        return $activity->created_by === $user->id
            || ($user->faculty !== null && $activity->faculty === $user->faculty);
    }
}
