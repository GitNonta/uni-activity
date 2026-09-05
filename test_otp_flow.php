<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

$email = 'nontawat2546.2546@gmail.com';

echo "=== Step 1: Clean tables ===" . PHP_EOL;
DB::table('password_reset_tokens')->delete();
DB::table('password_reset_otps')->delete();
echo "Tokens: " . DB::table('password_reset_tokens')->count() . PHP_EOL;
echo "OTPs: " . DB::table('password_reset_otps')->count() . PHP_EOL;

echo PHP_EOL . "=== Step 2: Send reset link (Password::broker staff) ===" . PHP_EOL;
$status = Password::broker('staff')->sendResetLink(['email' => $email]);
echo "Status: " . $status . PHP_EOL;
echo "Tokens in DB: " . DB::table('password_reset_tokens')->count() . PHP_EOL;

$tokenRecord = DB::table('password_reset_tokens')->where('email', $email)->first();
if ($tokenRecord) {
    echo "Token found: " . substr($tokenRecord->token, 0, 20) . "..." . PHP_EOL;
} else {
    echo "ERROR: No token found!" . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "=== Step 3: Simulate NewPasswordController::store() ===" . PHP_EOL;
$otp = (string) random_int(100000, 999999);
$lowerEmail = strtolower($email);
DB::table('password_reset_otps')->updateOrInsert(
    ['email' => $lowerEmail],
    [
        'otp' => $otp,
        'expires_at' => now()->addMinutes(10),
        'created_at' => now(),
        'updated_at' => now(),
    ]
);
echo "OTP saved: " . $otp . " for email: " . $lowerEmail . PHP_EOL;
echo "OTPs in DB: " . DB::table('password_reset_otps')->count() . PHP_EOL;

echo PHP_EOL . "=== Step 4: Simulate OtpVerificationController::verify() ===" . PHP_EOL;
$lookupEmail = strtolower($email);
$otpRecord = DB::table('password_reset_otps')
    ->where('email', $lookupEmail)
    ->first();
if ($otpRecord) {
    echo "OTP record found! OTP: " . $otpRecord->otp . " expires: " . $otpRecord->expires_at . PHP_EOL;
    $match = hash_equals((string) $otpRecord->otp, (string) $otp);
    echo "OTP match: " . ($match ? 'YES' : 'NO') . PHP_EOL;
} else {
    echo "ERROR: No OTP record found for email: " . $lookupEmail . PHP_EOL;
    echo "Table contents:" . PHP_EOL;
    $all = DB::table('password_reset_otps')->get();
    foreach ($all as $row) {
        echo "  - id: " . $row->id . " email: " . $row->email . " otp: " . $row->otp . PHP_EOL;
    }
}

echo PHP_EOL . "=== Step 5: Simulate Password::broker('staff')->reset() ===" . PHP_EOL;
$resetStatus = Password::broker('staff')->reset(
    [
        'email' => $email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
        'token' => $tokenRecord->token,
    ],
    function ($user, $password) {
        $user->forceFill([
            'password' => app('hash')->make($password),
            'remember_token' => Illuminate\Support\Str::random(60),
        ])->save();
        echo "  Password reset for user #" . $user->id . PHP_EOL;
    }
);
echo "Reset status: " . $resetStatus . PHP_EOL;
echo "Tokens remaining: " . DB::table('password_reset_tokens')->count() . PHP_EOL;

echo PHP_EOL . "=== DONE ===" . PHP_EOL;
