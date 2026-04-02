<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LeagueHistoryScraperService;

echo "🚀 Avvio Sincronizzazione Stagione 2021/22 (Premium)... \n";

try {
    $service = app(LeagueHistoryScraperService::class);
    $result = $service->scrapeSeason(2021);
    
    echo "----------------------------------------\n";
    print_r($result);
    echo "----------------------------------------\n";
    
    if ($result['status'] === 'success') {
        echo "✅ Sincronizzazione completata con successo!\n";
    } else {
        echo "❌ Errore durante la sincronizzazione.\n";
    }
} catch (\Exception $e) {
    echo "🔥 ECCEZIONE: " . $e->getMessage() . "\n";
}
