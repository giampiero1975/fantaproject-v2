<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\TeamDataService;

echo "🔄 AVVIO ALLINEAMENTO UFFICIALE (API -> DB) PER STAGIONE 2021/22...\n";

$service = app(TeamDataService::class);
try {
    $res = $service->importTeamsFromApi(2021);
    echo "\n📊 ESITO SINCRONIZZAZIONE:\n";
    echo "   ✅ Team Creati: " . ($res['created'] ?? 0) . "\n";
    echo "   ✅ Team Aggiornati/Adottati: " . ($res['updated'] ?? 0) . "\n";
    echo "\n🔎 Controlla il log 'storage/logs/Squadre/SquadreImport.log' per i dettagli sulle adozioni orfane.\n";
} catch (\Exception $e) {
    echo "\n❌ ERRORE: " . $e->getMessage() . "\n";
}
