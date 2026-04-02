<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;

$sbee = ProxyService::where('slug', 'scrapingbee')->first();
if ($sbee) {
    $sbee->api_key = 'YB2HKUTDLG7PXQYGWZBCVPSANAQLSSEDYD3T3GQ5Y5DZA4SVQFON5YI9QOBRY7EZPPZPJV86I20HEJXP';
    $sbee->is_active = true;
    $sbee->save();
    echo "ScrapingBee encrypted and restored via Eloquent!\n";
} else {
    echo "ScrapingBee not found!\n";
}
