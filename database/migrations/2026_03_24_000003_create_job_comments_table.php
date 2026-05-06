<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained('job_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('job_comments')->cascadeOnDelete(); // reply
            $table->text('body');
            $table->timestamps();

            $table->index('job_listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_comments');
    }
};
