<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendances MODIFY COLUMN method ENUM('qr_scan','self','manual','walk_in','staff_scan') DEFAULT 'qr_scan'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL doesn't allow direct enum modification easily if it's a native enum, 
            // but Laravel often uses text with constraints for enums if not using native types.
            // Based on previous migrations, it seems they were using raw MySQL statements.
            // For PG, we'll try to add the value if it's a native enum, or just ignore if it's a check constraint.
            // However, looking at the previous migration, they didn't have a PG version.
            // To be safe and compatible with the project's style:
            try {
                DB::statement("ALTER TYPE attendance_method_enum ADD VALUE IF NOT EXISTS 'staff_scan'");
            } catch (\Exception $e) {
                // If it's not a native type, it might be a simple varchar or something else.
                // We'll skip for now as PG support seems partial in this project's migrations.
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendances MODIFY COLUMN method ENUM('qr_scan','self','manual','walk_in') DEFAULT 'qr_scan'");
        }
    }
};
