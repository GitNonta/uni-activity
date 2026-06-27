<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['is_active', 'created_at'], 'idx_announcements_active_date');
        });

        Schema::table('job_listings', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_jobs_status_created');
            $table->index('created_at', 'idx_jobs_created');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->index('job_id', 'idx_rooms_job_id');
        });

        Schema::table('job_inquiries', function (Blueprint $table) {
            $table->index('created_at', 'idx_job_inquiries_created');
            $table->index('user_id', 'idx_job_inquiries_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('idx_announcements_active_date');
        });

        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropIndex('idx_jobs_status_created');
            $table->dropIndex('idx_jobs_created');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_job_id');
        });

        Schema::table('job_inquiries', function (Blueprint $table) {
            $table->dropIndex('idx_job_inquiries_created');
            $table->dropIndex('idx_job_inquiries_user_id');
        });
    }
};
