<?php
declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminAuditLogPolicy
{
    use HandlesAuthorization;

    /** Determine whether the user can view any audit logs. */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /** Determine whether the user can view a specific audit log. */
    public function view(User $user, AdminAuditLog $log): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}
