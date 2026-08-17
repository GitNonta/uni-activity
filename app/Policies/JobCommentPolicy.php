<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JobComment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobCommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the comment.
     * Owner of comment, Admin, or the Staff who created the JobListing can delete.
     */
    public function delete(User $user, JobComment $comment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($comment->user_id === $user->id) {
            return true;
        }

        return $user->isStaff() && $comment->jobListing?->created_by === $user->id;
    }
}
