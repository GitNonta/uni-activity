<?php

declare(strict_types=1);

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
        Schema::create('activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number')->default(1);
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('activity_hours', 5, 1)->default(0.0);
            $table->dateTime('checkin_open_at')->nullable();
            $table->dateTime('checkin_close_at')->nullable();
            $table->dateTime('checkout_open_at')->nullable();
            $table->dateTime('checkout_close_at')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'date']);
            $table->index(['activity_id', 'day_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_days');
    }
};
