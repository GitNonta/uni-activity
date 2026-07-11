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
            $table->boolean('allow_walkin')->default(true)->after('allow_early_checkin')->comment('อนุญาตให้นักศึกษาที่ไม่ได้ลงทะเบียนสแกนเข้างานได้ (Walk-in)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('allow_walkin');
        });
    }
};
