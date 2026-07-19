<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            // Liveness detection result
            $table->decimal('liveness_score', 5, 4)
                ->nullable()
                ->after('face_match_passed')
                ->comment('Passive liveness score 0.0000–1.0000');

            $table->boolean('liveness_passed')
                ->nullable()
                ->after('liveness_score')
                ->comment('True = face is live person, False = possible photo attack');

            // Pipeline info (audit trail)
            $table->string('detector_pipeline', 80)
                ->nullable()
                ->after('liveness_passed')
                ->comment('e.g. yolov8n-face+scrfd+arcface+liveness');

            $table->index('liveness_passed', 'idx_attendances_liveness_passed');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('idx_attendances_liveness_passed');
            $table->dropColumn([
                'liveness_score',
                'liveness_passed',
                'detector_pipeline',
            ]);
        });
    }
};
