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
            $table->dateTime('checkout_open_at')->nullable()->after('checkin_close_at');
            $table->dateTime('checkout_close_at')->nullable()->after('checkout_open_at');
            
            $table->index('checkout_open_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['checkout_open_at']);
            $table->dropColumn(['checkout_open_at', 'checkout_close_at']);
        });
    }
};
