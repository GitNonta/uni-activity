<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type', 50)->default('general'); // urgent, venue_change, reschedule, general
            $table->string('target_audience', 50)->default('approved'); // all, approved, waitlisted
            $table->json('channels')->nullable(); // ["in_app", "line"]
            $table->integer('recipients_count')->default(0);
            $table->integer('line_sent_count')->default(0);
            $table->timestamps();

            $table->index(['activity_id', 'created_at']);
        });

        Schema::create('activity_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('reminder_type', 20); // 24h, 2h
            $table->timestamp('sent_at')->useCurrent();
            $table->integer('recipients_count')->default(0);
            $table->integer('line_sent_count')->default(0);
            $table->timestamps();

            $table->unique(['activity_id', 'reminder_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_reminder_logs');
        Schema::dropIfExists('activity_broadcasts');
    }
};
