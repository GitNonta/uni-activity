<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->index(['category_id', 'status', 'activity_date'], 'idx_activities_category_status_date');
            $table->index(['scope', 'status', 'activity_date'], 'idx_activities_scope_status_date');
            $table->index(['created_by', 'created_at'], 'idx_activities_creator_created');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'is_active'], 'idx_users_role_active');
            $table->index(['faculty', 'department', 'role'], 'idx_users_faculty_dept_role');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'registered_at'], 'idx_regs_user_status_registered');
            $table->index(['status', 'registered_at'], 'idx_regs_status_registered');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'activity_id'], 'idx_att_user_status_activity');
            $table->index(['activity_id', 'status', 'checked_in_at'], 'idx_att_activity_status_checked');
            $table->index(['user_id', 'status', 'checked_in_at'], 'idx_att_user_status_checked');
        });

        Schema::table('notifications_custom', function (Blueprint $table): void {
            $table->index(['user_id', 'is_read', 'created_at'], 'idx_notifs_user_read_created');
        });
    }

    public function down(): void
    {
        Schema::table('notifications_custom', function (Blueprint $table): void {
            $table->dropIndex('idx_notifs_user_read_created');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('idx_att_user_status_activity');
            $table->dropIndex('idx_att_activity_status_checked');
            $table->dropIndex('idx_att_user_status_checked');
        });

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropIndex('idx_regs_user_status_registered');
            $table->dropIndex('idx_regs_status_registered');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_users_role_active');
            $table->dropIndex('idx_users_faculty_dept_role');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex('idx_activities_category_status_date');
            $table->dropIndex('idx_activities_scope_status_date');
            $table->dropIndex('idx_activities_creator_created');
        });
    }
};
