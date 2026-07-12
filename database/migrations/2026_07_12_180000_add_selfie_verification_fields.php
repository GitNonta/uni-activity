<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->string('selfie_photo_path')->nullable()->after('checkout_distance_meters');
            $table->decimal('face_match_score', 5, 2)->nullable()->after('selfie_photo_path');
            $table->boolean('face_match_passed')->nullable()->after('face_match_score');
            $table->boolean('selfie_reviewed')->default(false)->after('face_match_passed');
            $table->string('selfie_review_result')->nullable()->after('selfie_reviewed');
            $table->unsignedBigInteger('selfie_reviewed_by')->nullable()->after('selfie_review_result');

            $table->index('face_match_passed');
            $table->index('selfie_reviewed');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->boolean('require_selfie_verification')->default(false)->after('require_attendance_approval');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex(['face_match_passed']);
            $table->dropIndex(['selfie_reviewed']);
            $table->dropColumn([
                'selfie_photo_path',
                'face_match_score',
                'face_match_passed',
                'selfie_reviewed',
                'selfie_review_result',
                'selfie_reviewed_by',
            ]);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn('require_selfie_verification');
        });
    }
};
