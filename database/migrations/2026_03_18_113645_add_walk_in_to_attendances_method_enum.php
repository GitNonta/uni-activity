<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY COLUMN method ENUM('qr_scan','self','manual','walk_in') DEFAULT 'qr_scan'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY COLUMN method ENUM('qr_scan','self','manual') DEFAULT 'qr_scan'");
    }
};
