<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เปลี่ยน staff คนแรก (สร้างเก่าสุด) เป็น admin
 * หากยังไม่มี admin ในระบบ
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. แก้ไขประเภทคอลัมน์ ENUM ให้รองรับ 'admin' ก่อน (อิงตามฐานข้อมูล MySQL)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('student', 'staff', 'admin') DEFAULT 'student' NULL");
        }

        // 2. ถ้ายังไม่มี admin สักคน ให้ promote staff คนแรกเป็น admin
        $hasAdmin = DB::table('users')->where('role', 'admin')->exists();
        if (!$hasAdmin) {
            $firstStaff = DB::table('users')
                ->where('role', 'staff')
                ->orderBy('id')
                ->first();

            if ($firstStaff) {
                DB::table('users')
                    ->where('id', $firstStaff->id)
                    ->update(['role' => 'admin']);
            }
        }
    }

    public function down(): void
    {
        // rollback: เปลี่ยน admin ทั้งหมดกลับเป็น staff
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'staff']);
    }
};
