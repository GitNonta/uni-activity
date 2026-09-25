<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->string('faculty', 100)->nullable()->index();
            $table->string('department', 100)->nullable()->index();
            $table->unsignedSmallInteger('academic_year_start')->nullable();
            $table->decimal('min_total_hours', 6, 1)->default(100.0);
            $table->decimal('min_mandatory_hours', 6, 1)->default(0.0);
            $table->json('scope_requirements')->nullable(); // e.g. {"university": 40, "faculty": 40}
            $table->json('category_requirements')->nullable(); // e.g. {"volunteer": 20, "academic": 10}
            $table->boolean('require_all_mandatory_activities')->default(true);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_criteria');
    }
};
