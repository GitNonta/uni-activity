<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            // Keep job_id after the job is deleted so the room becomes a
            // read-only archive instead of silently morphing into a fake
            // "direct" room (the old FK nulled job_id on delete).
            $table->dropForeign(['job_id']);

            // Snapshot of the job creator so staff can still see their
            // threads after the job row is gone.
            $table->foreignId('creator_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creator_id');
            $table->foreign('job_id')
                ->references('id')
                ->on('job_listings')
                ->nullOnDelete();
        });
    }
};
