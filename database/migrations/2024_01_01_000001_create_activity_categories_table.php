<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('required_hours', 4, 1)->default(0);
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->default('#3B82F6');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_categories');
    }
};
