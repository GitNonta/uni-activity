<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('activity_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('activity_hours', 4, 1)->default(0);
            $table->integer('max_participants')->default(0);
            $table->dateTime('register_open_at');
            $table->dateTime('register_close_at');
            $table->dateTime('checkin_open_at');
            $table->dateTime('checkin_close_at');
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('allow_early_checkin')->default(false);
            $table->foreignId('category_id')->nullable()->constrained('activity_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('qr_token', 64)->unique()->nullable();
            $table->string('image_path')->nullable();
            $table->enum('status', ['upcoming', 'open', 'full', 'ongoing', 'done', 'cancelled'])->default('upcoming');
            $table->timestamps();

            $table->index(['status', 'activity_date']);
            $table->index('register_open_at');
            $table->index('checkin_open_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
