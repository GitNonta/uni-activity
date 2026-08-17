<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnnouncementPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any announcements in admin panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can view the announcement.
     */
    public function view(?User $user, Announcement $announcement): bool
    {
        if (!$user) {
            return $announcement->is_active && ($announcement->target_faculty === null || $announcement->target_faculty === '');
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return true;
        }

        // Student: must be active and target audience
        if (!$announcement->is_active) {
            return false;
        }

        if ($announcement->target_faculty !== null && $announcement->target_faculty !== '') {
            return $announcement->target_faculty === $user->faculty;
        }

        return true;
    }

    /**
     * Determine whether the user can create announcements.
     */
    public function create(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can update the announcement.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $announcement->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the announcement.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $announcement->created_by === $user->id;
    }

    /**
     * Determine whether the user can toggle active status.
     */
    public function toggle(User $user, Announcement $announcement): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $announcement->created_by === $user->id;
    }
}
