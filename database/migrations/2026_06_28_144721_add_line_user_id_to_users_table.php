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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('line_user_id')->nullable()->unique()->after('profile_photo');
            $table->string('line_display_name')->nullable()->after('line_user_id');
            $table->boolean('line_notify_enabled')->default(true)->after('line_display_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['line_user_id', 'line_display_name', 'line_notify_enabled']);
        });
    }
};
