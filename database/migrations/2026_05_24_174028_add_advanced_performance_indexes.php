<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Composite index for activity listing and filtering
            $table->index(['status', 'activity_date'], 'idx_activities_status_date');
            // Index for faculty-based filtering
            $table->index(['scope', 'faculty', 'department'], 'idx_activities_scope_faculty');
        });

        Schema::table('registrations', function (Blueprint $table) {
            // Faster lookup for "My Activities" page
            $table->index(['user_id', 'status', 'registered_at'], 'idx_regs_user_status_date');
            // Faster lookup for admin participant management
            $table->index(['activity_id', 'status'], 'idx_regs_act_status');
        });

        Schema::table('attendances', function (Blueprint $table) {
            // Common check: has this user checked in/out of this activity?
            $table->index(['user_id', 'activity_id', 'status'], 'idx_att_user_act_status');
            // Date-based reporting
            $table->index(['checked_in_at', 'status'], 'idx_att_date_status');
        });

        Schema::table('notifications_custom', function (Blueprint $table) {
            // Polling optimization: get unread notifications for a user
            $table->index(['user_id', 'is_read', 'created_at'], 'idx_notif_user_unread');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Dashboard optimization
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_activities_status_date');
            $table->dropIndex('idx_activities_scope_faculty');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex('idx_regs_user_status_date');
            $table->dropIndex('idx_regs_act_status');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_att_user_act_status');
            $table->dropIndex('idx_att_date_status');
        });

        Schema::table('notifications_custom', function (Blueprint $table) {
            $table->dropIndex('idx_notif_user_unread');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
