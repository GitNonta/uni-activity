<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE attendances MODIFY COLUMN method ENUM('qr_scan','self','manual','walk_in') DEFAULT 'qr_scan'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE attendances MODIFY COLUMN method ENUM('qr_scan','self','manual') DEFAULT 'qr_scan'");
    }
};
