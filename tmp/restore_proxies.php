<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;

$sbee = ProxyService::where('slug', 'scrapingbee')->first();
if ($sbee) {
    $sbee->update([
        'api_key' => 'YB2HKUTDLG7PXQYGWZBCVPSANAQLSSEDYD3T3GQ5Y5DZA4SVQFON5YI9QOBRY7EZPPZPJV86I20HEJXP',
        'is_active' => true,
    ]);
    echo "ScrapingBee restored!\n";
}

ProxyService::query()->update(['is_active' => true]);
echo "All proxies re-activated!\n";
