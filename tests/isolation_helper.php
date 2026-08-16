<?php

declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$action = $argv[1] ?? 'seed';

if ($action === 'seed') {
    $uA = App\Models\User::factory()->create([
        'student_id' => 'ISO_A_' . rand(100000, 999999),
        'full_name'  => 'ISO_TEST_STUDENT_A',
        'role'       => 'student',
        'is_active'  => true,
    ]);
    $uB = App\Models\User::factory()->create([
        'student_id' => 'ISO_B_' . rand(100000, 999999),
        'full_name'  => 'ISO_TEST_STUDENT_B',
        'role'       => 'student',
        'is_active'  => true,
    ]);

    $tokenA = $uA->createToken('isolation-test')->plainTextToken;
    $tokenB = $uB->createToken('isolation-test')->plainTextToken;

    echo json_encode([
        'id_a' => $uA->id,
        'id_b' => $uB->id,
        'token_a' => $tokenA,
        'token_b' => $tokenB,
    ]);
} elseif ($action === 'cleanup') {
    $idA = (int)($argv[2] ?? 0);
    $idB = (int)($argv[3] ?? 0);
    App\Models\User::whereIn('id', [$idA, $idB])->delete();
    echo "CLEANUP_OK";
}
