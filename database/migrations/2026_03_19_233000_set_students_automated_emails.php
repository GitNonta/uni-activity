<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // อัปเดตอีเมลนักศึกษาที่ยังว่างอยู่ ให้เป็น s[student_id]@pkru.ac.th
        $students = User::where('role', 'student')
            ->whereNotNull('student_id')
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            })
            ->get();

        foreach ($students as $student) {
            $student->email = 's' . $student->student_id . '@pkru.ac.th';
            $student->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to reverse this without losing original emails if they were blank
    }
};
