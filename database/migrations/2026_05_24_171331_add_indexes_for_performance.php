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
        Schema::table('activities', function (Blueprint $table) {
            $table->index('activity_date');
            $table->index('status');
            $table->index('category_id');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->index('status');
            $table->index(['user_id', 'activity_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index('status');
            $table->index(['user_id', 'activity_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['activity_date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['category_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'activity_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'activity_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
        });
    }
};
