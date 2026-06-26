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
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            $table->string('checkout_method')->nullable()->after('method'); // qr_scan, self, manual, etc.
            $table->decimal('checkout_latitude', 10, 7)->nullable()->after('checkin_longitude');
            $table->decimal('checkout_longitude', 10, 7)->nullable()->after('checkout_latitude');
            $table->decimal('checkout_distance_meters', 10, 2)->nullable()->after('distance_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'checked_out_at',
                'checkout_method',
                'checkout_latitude',
                'checkout_longitude',
                'checkout_distance_meters'
            ]);
        });
    }
};
