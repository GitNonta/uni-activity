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
        Schema::table('activities', function (Blueprint $table) {
            $table->enum('scope', ['university', 'faculty', 'department'])->default('university')->after('status');
            $table->string('faculty', 100)->nullable()->after('scope');
            $table->string('department', 100)->nullable()->after('faculty');

            $table->index('scope');
            $table->index('faculty');
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropIndex(['faculty']);
            $table->dropIndex(['department']);
            $table->dropColumn(['scope', 'faculty', 'department']);
        });
    }
};
