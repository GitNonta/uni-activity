<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\JobComment;
use App\Models\JobListing;
use App\Models\Message;
use App\Models\Registration;
use App\Models\Room;
use App\Models\User;
use App\Policies\ActivityCategoryPolicy;
use App\Policies\ActivityPolicy;
use App\Policies\AdminAuditLogPolicy;
use App\Policies\AnnouncementPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\JobCommentPolicy;
use App\Policies\JobListingPolicy;
use App\Policies\MessagePolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\RoomPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        Activity::class         => ActivityPolicy::class,
        Attendance::class       => AttendancePolicy::class,
        Registration::class     => RegistrationPolicy::class,
        JobListing::class       => JobListingPolicy::class,
        JobComment::class       => JobCommentPolicy::class,
        Room::class             => RoomPolicy::class,
        Message::class          => MessagePolicy::class,
        Announcement::class     => AnnouncementPolicy::class,
        User::class             => UserPolicy::class,
        ActivityCategory::class => ActivityCategoryPolicy::class,
        AdminAuditLog::class    => AdminAuditLogPolicy::class,
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
