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
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('is_multiday')->default(false)->after('activity_date');
            $table->date('end_date')->nullable()->after('is_multiday');
            $table->decimal('min_hours_before_checkout', 5, 1)->nullable()->after('activity_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['is_multiday', 'end_date', 'min_hours_before_checkout']);
        });
    }
};
