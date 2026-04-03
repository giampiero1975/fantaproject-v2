<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;
use App\Services\ProxyProviders\ZenRowsProvider;
use Illuminate\Support\Facades\Http;

$proxy = ProxyService::where('name', 'ZenRows')->first();
$url = 'https://fbref.com/en/squads/dc56fe14/Milan-Stats';
$proxyUrl = app(ZenRowsProvider::class)->getProxyUrl($proxy, $url);

echo "Testing URL: $url\n";
echo "Proxy URL: $proxyUrl\n";

try {
    $response = Http::timeout(60)->get($proxyUrl);
    echo "Status: " . $response->status() . "\n";
    echo "Body length: " . strlen($response->body()) . "\n";
    if ($response->status() !== 200) {
        echo "Error: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
