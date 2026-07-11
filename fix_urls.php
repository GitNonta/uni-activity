<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$messages = App\Models\Message::whereNotNull('attachments')->get();
$count = 0;
foreach($messages as $msg) {
    $atts = $msg->attachments;
    $changed = false;
    foreach($atts as $k => $att) {
        if (isset($att['url']) && strpos($att['url'], 'http') !== false) {
            $atts[$k]['url'] = '/storage/' . $att['path'];
            $changed = true;
        }
    }
    if ($changed) {
        $msg->attachments = $atts;
        $msg->save();
        $count++;
    }
}
echo "Fixed $count messages\n";
