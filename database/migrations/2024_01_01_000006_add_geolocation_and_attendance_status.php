<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มระบบพิกัดสถานที่ (geolocation) ในกิจกรรม
 * และเพิ่มสถานะอนุมัติ (status) ในตาราง attendances
 */
return new class extends Migration
{
    public function up(): void
    {
        // เพิ่มพิกัดและรัศมีในตาราง activities เพื่อกำหนดขอบเขตสถานที่จัดกิจกรรม
        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('checkin_radius')->default(200)->after('longitude'); // หน่วยเมตร
        });

        // เพิ่มสถานะและพิกัดเช็คอินในตาราง attendances
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('method');
            $table->decimal('checkin_latitude', 10, 7)->nullable()->after('ip_address');
            $table->decimal('checkin_longitude', 10, 7)->nullable()->after('checkin_latitude');
            $table->decimal('distance_meters', 8, 2)->nullable()->after('checkin_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'checkin_radius']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['status', 'checkin_latitude', 'checkin_longitude', 'distance_meters']);
        });
    }
};
