<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any attendances.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can view the attendance.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($attendance->user_id === $user->id) {
            return true;
        }

        return $this->canManageActivity($user, $attendance->activity);
    }

    /**
     * Determine whether the user can approve the attendance.
     */
    public function approve(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canManageActivity($user, $attendance->activity);
    }

    /**
     * Determine whether the user can reject the attendance.
     */
    public function reject(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canManageActivity($user, $attendance->activity);
    }

    /**
     * Determine whether the user can update the attendance.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canManageActivity($user, $attendance->activity);
    }

    /**
     * Determine whether the user can delete the attendance.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canManageActivity($user, $attendance->activity);
    }

    /**
     * Determine whether the user can perform manual check-in for an activity.
     */
    public function manualCheckIn(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canManageActivity($user, $activity);
    }

    /**
     * Determine whether the user can review selfie for the attendance.
     */
    public function reviewSelfie(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->canManageActivity($user, $attendance->activity);
    }

    private function canManageActivity(User $user, ?Activity $activity): bool
    {
        if (!$user->isStaff() || $activity === null) {
            return false;
        }

        return $activity->created_by === $user->id
            || ($user->faculty !== null && $activity->faculty === $user->faculty);
    }
}
