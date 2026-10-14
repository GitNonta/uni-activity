<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 30)->default('promptpay');
            $table->string('promptpay_id', 20)->nullable();
            $table->string('ref', 30)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'status']);
            $table->unique(['user_id', 'activity_id', 'amount'], 'payments_user_activity_amount_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
