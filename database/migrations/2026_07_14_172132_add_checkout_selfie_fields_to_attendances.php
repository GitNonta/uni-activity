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
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('checkout_selfie_photo_path')->nullable()->after('selfie_reviewed_by');
            $table->decimal('checkout_face_match_score', 5, 2)->nullable()->after('checkout_selfie_photo_path');
            $table->boolean('checkout_face_match_passed')->nullable()->after('checkout_face_match_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'checkout_selfie_photo_path',
                'checkout_face_match_score',
                'checkout_face_match_passed',
            ]);
        });
    }
};
