<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Services\TeamDataService;
use Illuminate\Support\Facades\DB;

echo "🧪 AVVIO TEST ADOZIONE ORFANI...\n";

// 1. Prepariamo un "Orfano" di test (come se fosse stato creato da FBref in passato)
$orphanName = "Spezia Calcio";
$apiName = "Spezia";
$apiId = 8888; // Un ID di fantasia

$orphan = Team::where('name', $orphanName)->first();
if (!$orphan) {
    echo "🆕 Creo l'orfano di test: $orphanName\n";
    $orphan = Team::create(['name' => $orphanName, 'api_id' => null, 'fbref_id' => 'fbref-spezia-123']);
} else {
    echo "⚠️ L'orfano $orphanName esiste già. Mi assicuro che api_id sia NULL.\n";
    $orphan->update(['api_id' => null]);
}

echo "🔍 Simulazione arrivo da API: Nome='$apiName', ID=$apiId\n";

$service = app(TeamDataService::class);

// Simuliamo la logica che abbiamo inserito nel service
$foundId = $service->findOrphanIdByName($apiName);

if ($foundId && $foundId === $orphan->id) {
    echo "✅ SUCCESSO: Il Trait ha identificato l'orfano '{$orphan->name}' per il nome API '{$apiName}'!\n";
    
    // Testiamo l'aggiornamento (Adozione)
    $team = Team::updateOrCreate(
        ['id' => $foundId],
        ['api_id' => $apiId, 'name' => $apiName]
    );
    
    if ($team->id === $orphan->id && $team->api_id === $apiId) {
        echo "💎 ADOZIONE COMPLETATA: Il record esistente è stato promosso a ufficiale senza duplicati.\n";
        echo "   ID Finale: {$team->id}, Nome: {$team->name}, API ID: {$team->api_id}, FBref ID: {$team->fbref_id}\n";
    }
} else {
    echo "❌ FALLIMENTO: L'orfano non è stato riconosciuto.\n";
}

// Pulizia (opzionale, ma lasciamolo per ora se vuoi vedere il risultato a DB)
// $orphan->delete();
