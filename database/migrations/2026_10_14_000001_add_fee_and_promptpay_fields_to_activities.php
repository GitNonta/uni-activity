<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->default(0)->after('checkin_close_at');
            }
            if (!Schema::hasColumn('activities', 'promptpay_id')) {
                $table->string('promptpay_id', 20)->nullable()->after('fee_amount');
            }
            if (!Schema::hasColumn('activities', 'payment_required')) {
                $table->boolean('payment_required')->default(false)->after('promptpay_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            foreach (['payment_required', 'promptpay_id', 'fee_amount'] as $col) {
                if (Schema::hasColumn('activities', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
