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
            // ปรับขนาดของ distance_meters ให้รองรับระยะทางที่กว้างขึ้น (เช่น 999,999,999.99 เมตร)
            // เพื่อหลีกเลี่ยงข้อผิดพลาด 'Numeric value out of range' เมื่อนักศึกษาเช็คอินจากที่ไกลๆ
            $table->decimal('distance_meters', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('distance_meters', 8, 2)->change();
        });
    }
};
