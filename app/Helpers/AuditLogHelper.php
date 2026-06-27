<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

if (!function_exists('log_action')) {
    /**
     * Log an admin action.
     *
     * @param string $action       Action type (create, update, delete, approve, reject, toggle, login, logout, etc.)
     * @param string|null $modelType Model class name (e.g., Activity::class) or null
     * @param int|null $modelId    Model ID affected
     * @param string $description  Human‑readable description of the action
     * @param array|null $oldValues Old attribute values (for update/delete)
     * @param array|null $newValues New attribute values (for create/update)
     */
    function log_action(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        string $description = '',
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $user = Auth::user();
        if (!$user) {
            return; // not authenticated, ignore
        }

        $data = [
            'user_id'      => $user->getKey(),
            'action'       => $action,
            'model_type'   => $modelType,
            'model_id'     => $modelId,
            'description'  => $description,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'ip_address'   => Request::ip(),
            'user_agent'   => Request::header('User-Agent'),
        ];

        // Use repository to keep transaction handling in one place
        app(\App\Repositories\AdminAuditLogRepository::class)->create($data);
    }
}
