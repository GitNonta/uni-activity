<?php
/**
 * OPcache Preloader — run after deploy to warm cache
 * Usage: php scripts/preload.php
 */

$base = dirname(__DIR__);
$dirs = [
    $base . '/vendor/laravel/framework/src/Illuminate',
    $base . '/vendor/sanctum',
    $base . '/app',
];

$count = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            opcache_compile_file($file->getPathname());
            $count++;
        }
    }
}

echo "Preloaded {$count} PHP files into OPcache\n";
