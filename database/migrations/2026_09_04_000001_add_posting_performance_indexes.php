<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the posting system.
 *
 * - announcements (is_active, published_at): the student list filters
 *   is_active = true AND published_at <= now() on every page load.
 *   Unindexed, the database scans the whole table on each visit.
 *
 * - announcements (created_by): admin list scoping + creator eager load.
 *
 * Note: users.role is already indexed (users_role_index +
 * idx_users_role_active), so the ChatService default-admin lookup is
 * covered by existing migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->index(['is_active', 'published_at']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'published_at']);
            $table->dropIndex(['created_by']);
        });
    }
};
