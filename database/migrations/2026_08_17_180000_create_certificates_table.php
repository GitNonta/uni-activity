<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->string('certificate_code')->unique()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('activity_categories')->nullOnDelete();
            $table->string('title');
            $table->decimal('hours_completed', 8, 2)->default(0);
            $table->string('academic_year', 10)->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->string('verification_token', 64)->unique()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
