<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can access/view this chat room.
     */
    public function view(User $user, Room $room): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Is direct participant in the room
        if ($room->users()->where('users.id', $user->id)->exists()) {
            return true;
        }

        // Staff who created the job linked to this room
        return $user->isStaff() && $room->job?->created_by === $user->id;
    }

    /**
     * Determine whether the user can send messages in this room.
     */
    public function sendMessage(User $user, Room $room): bool
    {
        return $this->view($user, $room);
    }

    /**
     * Determine whether the user can delete this chat thread.
     */
    public function delete(User $user, Room $room): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff() && $room->job?->created_by === $user->id;
    }
}
