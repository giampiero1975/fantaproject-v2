<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\TeamDataService;

$service = app(TeamDataService::class);
$pisaApiId = 1107; // ID ufficiale Pisa su Football-Data
$year = 2023;

echo "--- ANALISI API FOOTBALL-DATA: PISA [Season $year] ---\n";

try {
    $response = $service->getSquad($pisaApiId, $year);
    
    if (empty($response)) {
        echo "L'API ha restituito una rosa VUOTA.\n";
    } else {
        echo "Rosa ricevuta! Numero giocatori: " . count($response['squad'] ?? []) . "\n";
        echo "Data Ultimo Aggiornamento API: " . ($response['lastUpdated'] ?? 'N/D') . "\n";
        
        echo "\nPrimi 5 giocatori restituiti:\n";
        $squad = array_slice($response['squad'] ?? [], 0, 5);
        foreach ($squad as $p) {
            echo "- " . $p['name'] . " (ID: " . $p['id'] . ")\n";
        }
        
        echo "\nJSON COMPLETO (prime 2000 righe):\n";
        echo substr(json_encode($response, JSON_PRETTY_PRINT), 0, 2000) . "...\n";
    }
} catch (\Exception $e) {
    echo "ERRORE CHIAMATA API: " . $e->getMessage() . "\n";
}
