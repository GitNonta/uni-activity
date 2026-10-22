<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$guard = auth()->guard('web');
$reflect = new ReflectionMethod(get_class($guard), 'getName');
$reflect->setAccessible(true);
$loginKey = $reflect->invoke($guard);

$admin = App\Models\User::where('role', 'staff')->first() ?: App\Models\User::first();
if (!$admin) { echo "NO USER\n"; exit(1); }

$pwHash = $admin->getAuthPassword();
$sessionData = [
    '_token'            => Str::random(40),
    'locale'            => 'en',
    $loginKey           => $admin->id,
    'password_hash_web' => hash('sha256', is_string($pwHash) ? $pwHash : ''),
];

$id = Str::random(40);
DB::table('sessions')->insert([
    'id'            => $id,
    'user_id'       => $admin->id,
    'ip_address'    => '127.0.0.1',
    'user_agent'    => 'e2e-admin-session',
    'payload'       => base64_encode(serialize($sessionData)),
    'last_activity' => time(),
]);

$enc = app('encrypter');
$cookieName = config('session.cookie');
$prefix = Illuminate\Cookie\CookieValuePrefix::create($cookieName, $enc->getKey());
$value = $prefix . $enc->encrypt($id, false);

echo "SESSION_ID={$id}\n";
echo "COOKIE_NAME={$cookieName}\n";
echo "COOKIE_VALUE={$value}\n";
