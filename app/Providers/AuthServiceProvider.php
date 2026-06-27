<?php
declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminAuditLog;
use App\Policies\AdminAuditLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        AdminAuditLog::class => AdminAuditLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Optional gate for admin role
        Gate::define('admin-only', function ($user) {
            return $user->hasRole('admin')
                ? Response::allow()
                : Response::deny('You are not an admin');
        });
    }
}
