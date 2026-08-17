<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JobListing;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobListingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any jobs in admin panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can view the job in admin panel.
     */
    public function view(User $user, JobListing $job): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $job->created_by === $user->id;
    }

    /**
     * Determine whether the user can create jobs.
     */
    public function create(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can update the job.
     */
    public function update(User $user, JobListing $job): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $job->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the job.
     */
    public function delete(User $user, JobListing $job): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $job->created_by === $user->id;
    }

    /**
     * Determine whether the user can update status / manage applicants / export applicants.
     */
    public function manage(User $user, JobListing $job): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $job->created_by === $user->id;
    }

    /**
     * Determine whether the student can apply to this job.
     */
    public function apply(User $user, JobListing $job): bool
    {
        return $user->role === 'student' && $job->isOpen() && $job->hasAvailableSlots();
    }
}
