<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ── [tag:2026-10-14-translation] เพิ่มคอลัมน์ _en สำหรับ cache คำแปล EN (กิจกรรม) ──
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->longText('description_en')->nullable()->after('description');
            $table->string('location_en')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en', 'location_en']);
        });
    }
};
