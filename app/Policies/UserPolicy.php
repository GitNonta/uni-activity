<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view user list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can view the specific user/student profile.
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can view student profiles
        if ($user->isStaff() && $targetUser->role === 'student') {
            return true;
        }

        return $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $targetUser): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        return $user->isAdmin() && $user->id !== $targetUser->id;
    }

    /**
     * Determine whether the user can toggle active status of the target user.
     */
    public function toggle(User $user, User $targetUser): bool
    {
        return $user->isAdmin() && $user->id !== $targetUser->id;
    }

    /**
     * Determine whether the user can manage student attendances (manual add/update/delete).
     */
    public function manageAttendance(User $user, User $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $student->role === 'student';
    }

    /**
     * Determine whether the user can send direct message to the student.
     */
    public function sendMessage(User $user, User $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $student->role === 'student';
    }
}
