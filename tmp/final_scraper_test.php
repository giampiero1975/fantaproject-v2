<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProxyService;
use Illuminate\Support\Facades\Artisan;

// 1. Attiviamo solo ScraperAPI
ProxyService::query()->update(['is_active' => false]);
$s = ProxyService::where('slug', 'scraperapi')->first();
if ($s) {
    $s->update(['is_active' => true]);
    echo "✅ ScraperAPI Attivato.\n";
}

// 2. Lanciamo il comando di test
echo "🔍 Avvio test ottimizzato per FBref...\n";
Artisan::call('proxies:test-all', [
    '--url' => 'https://fbref.com/en/comps/11/2022-2023/2022-2023-Serie-A-Stats'
]);

echo Artisan::output();
