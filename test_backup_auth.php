<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

// Try to render the backups page as if logged in
$route = app('router')->getRoutes()->match(
    Illuminate\Http\Request::create('/admin/backups', 'GET')
);

if ($route) {
    // Fake auth
    $user = \App\Models\User::where('role', 'admin')->first();
    if (!$user) {
        $user = \App\Models\User::where('role', 'super_admin')->first();
    }
    
    $request = Illuminate\Http\Request::create('/admin/backups', 'GET');
    if ($user) {
        $request->setUserResolver(fn() => $user);
    }
    
    try {
        $response = app()->make(\Illuminate\Routing\Router::class)
            ->dispatch($request);
        echo "Status: " . $response->getStatusCode() . "\n";
        $content = $response->getContent();
        if (strlen($content) < 2000) {
            echo $content;
        } else {
            echo substr($content, 0, 1000) . "\n...[truncated]...\n";
            // Look for error messages
            if (preg_match('/<title>(.*?)<\/title>/i', $content, $m)) {
                echo "TITLE: " . $m[1] . "\n";
            }
            if (preg_match('/class="exception_title">(.*?)<\/h1>/i', $content, $m)) {
                echo "EXCEPTION: " . $m[1] . "\n";
            }
            if (preg_match('/Whoops/i', $content)) {
                echo "WHOOPS ERROR PAGE DETECTED\n";
            }
            // Extract error from debug page
            if (preg_match('/<span class="exc-title-primary">(.*?)<\/span>/i', $content, $m)) {
                echo "ERROR: " . strip_tags($m[1]) . "\n";
            }
            if (preg_match('/<span class="exc-title-secondary">(.*?)<\/span>/i', $content, $m)) {
                echo "FILE: " . strip_tags($m[1]) . "\n";
            }
        }
    } catch (\Throwable $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
} else {
    echo "Route not found\n";
}
