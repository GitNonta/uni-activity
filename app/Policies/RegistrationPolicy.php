<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegistrationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any registrations.
     */
    public function viewAny(User $user): bool
    {
        return $user->isStaffOrAdmin();
    }

    /**
     * Determine whether the user can view the registration.
     */
    public function view(User $user, Registration $registration): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($registration->user_id === $user->id) {
            return true;
        }

        return $user->isStaff() && $registration->activity?->created_by === $user->id;
    }

    /**
     * Determine whether the user can cancel/delete the registration.
     */
    public function delete(User $user, Registration $registration): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $registration->user_id === $user->id;
    }

    /**
     * Determine whether the user can approve the registration.
     */
    public function approve(User $user, Registration $registration): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $registration->activity?->created_by === $user->id;
    }

    /**
     * Determine whether the user can reject the registration.
     */
    public function reject(User $user, Registration $registration): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $registration->activity?->created_by === $user->id;
    }
}
