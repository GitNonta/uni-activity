<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the message's latest content.
     */
    public function view(User $user, Message $message): bool
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        // Sender or member of the message's room
        return $message->user_id === $user->id
            || $message->room?->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update the message.
     */
    public function update(User $user, Message $message): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $message->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the message.
     */
    public function delete(User $user, Message $message): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($message->user_id === $user->id) {
            return true;
        }

        // Staff owner of the room's job can moderate messages
        return $user->isStaff() && $message->room?->job?->created_by === $user->id;
    }
}
