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
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            Schema::table('registrations', function (Blueprint $table) {
                $table->enum('status', ['pending', 'approved', 'cancelled', 'rejected', 'completed'])->default('pending')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'cancelled', 'rejected'])->default('pending')->change();
        });
    }
};
