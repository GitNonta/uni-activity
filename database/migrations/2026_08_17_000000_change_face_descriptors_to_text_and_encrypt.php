<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Alters face descriptor columns to text and re-encrypts any existing plaintext biometric data.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('face_descriptor')->nullable()->change();
            $table->text('face_descriptor_js')->nullable()->change();
        });

        // Migrate and encrypt any existing unencrypted JSON records
        $users = DB::table('users')
            ->whereNotNull('face_descriptor')
            ->orWhereNotNull('face_descriptor_js')
            ->get();

        foreach ($users as $user) {
            $updates = [];
            
            if (!empty($user->face_descriptor)) {
                $val = trim((string)$user->face_descriptor);
                if (str_starts_with($val, '[') || str_starts_with($val, '{')) {
                    $updates['face_descriptor'] = Crypt::encryptString($val);
                }
            }

            if (!empty($user->face_descriptor_js)) {
                $valJs = trim((string)$user->face_descriptor_js);
                if (str_starts_with($valJs, '[') || str_starts_with($valJs, '{')) {
                    $updates['face_descriptor_js'] = Crypt::encryptString($valJs);
                }
            }

            if (!empty($updates)) {
                DB::table('users')->where('id', $user->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('face_descriptor')->nullable()->change();
            $table->text('face_descriptor_js')->nullable()->change();
        });
    }
};
