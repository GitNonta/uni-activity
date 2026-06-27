<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\AdminAuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminAuditLogRepository
{
    /**
     * Paginate audit logs with eager loading of the user.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return AdminAuditLog::with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get the model class name for query building.
     */
    public function model(): string
    {
        return AdminAuditLog::class;
    }


    /**
     * Get a new query builder for the audit log model.
     */
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return AdminAuditLog::query();
    }

    public function create(array $data): AdminAuditLog
    {
        return DB::transaction(function () use ($data) {
            return AdminAuditLog::create($data);
        });
    }
}
