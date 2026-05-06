<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มฟิลด์ข้อมูลส่วนตัวของเจ้าหน้าที่ (staff/admin)
 * เช่น เบอร์โทร, ตำแหน่ง, สังกัด/หน่วยงาน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('position', 100)->nullable()->after('phone');          // ตำแหน่ง เช่น นักวิชาการคอมพิวเตอร์
            $table->string('organization', 150)->nullable()->after('position');   // สังกัด/หน่วยงาน เช่น สำนักวิทยบริการฯ
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'position', 'organization']);
        });
    }
};
