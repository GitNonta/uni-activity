<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    $view = view('admin.backups.index', [
        'backups' => [],
        'totalSize' => 0,
        'formattedTotalSize' => '0 B',
        'latestBackup' => null,
        'scheduleInfo' => [
            'daily_db' => '1am',
            'weekly_full' => 'Sun 2am',
            'daily_clean' => '3am',
            'retention_days' => 14,
            'keep_minimum' => 5,
        ],
        'diskTotal' => 1000,
        'diskFree' => 500,
        'diskUsed' => 500,
        'diskPercent' => 50.0,
        'formattedDiskUsed' => '500 B',
        'formattedDiskTotal' => '1000 B',
        'formattedDiskFree' => '500 B',
    ]);
    echo $view->render();
    echo "\n\n=== RENDER OK ===\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
