<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;

$s = ProxyService::find(6);
if ($s) {
    echo "Found ID 6: " . $s->name . "\n";
    $s->api_key = 'YB2HKUTDLG7PXQYGWZBCVPSANAQLSSEDYD3T3GQ5Y5DZA4SVQFON5YI9QOBRY7EZPPZPJV86I20HEJXP';
    $s->is_active = 1;
    $s->save();
    echo "ScrapingBee (ID 6) updated and encrypted.\n";
} else {
    echo "Proxy ID 6 NOT FOUND!\n";
}
unlink(__FILE__);
