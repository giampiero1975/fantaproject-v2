<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;

$s = ProxyService::find(6);
if ($s) {
    $s->api_key = 'YB2HKUTDLG7PXQYGWZBCVPSANAQLSSEDYD3T3GQ5Y5DZA4SVQFON5YI9QOBRY7EZPPZPJV86I20HEJXP';
    $s->is_active = 1;
    $s->save();
    echo "ScrapingBee (ID 6) Activated and Encrypted via File!\n";
} else {
    echo "ID 6 not found.\n";
}
unlink(__FILE__);
