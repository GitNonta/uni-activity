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
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('method');
        });
        
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('method', ['qr_scan', 'self', 'manual'])->default('qr_scan')->after('activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('method');
        });
        
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('method', ['qr_scan', 'self', 'manual'])->default('qr_scan')->after('activity_id');
        });
    }
};
