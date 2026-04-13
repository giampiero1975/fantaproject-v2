<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- 🔍 TEST FALLBACK COMPETIZIONE (BOLOGNA 2024) ---\n";

$apiKey = config('services.player_stats_api.providers.football_data_org.api_key');
$season = 2024;
$url = "https://api.football-data.org/v4/competitions/SA/teams?season={$season}";

echo "Chiamata a: $url\n";

$response = Http::withHeaders(['X-Auth-Token' => $apiKey])->get($url);

if ($response->successful()) {
    $data = $response->json();
    $teams = $data['teams'] ?? [];
    
    foreach ($teams as $t) {
        if ($t['id'] == 103) {
            $squadCount = isset($t['squad']) ? count($t['squad']) : 'MANCANTE';
            echo "Team: {$t['name']} | Squad Count: $squadCount\n";
            if ($squadCount > 0) {
                echo "✅ TROVATO! L'endpoint competizione CONTIENE la rosa.\n";
            } else {
                echo "❌ Neanche l'endpoint competizione ha la rosa per questo team.\n";
            }
            break;
        }
    }
} else {
    echo "Errore API: " . $response->status() . "\n";
}

echo "--- 🏁 FINE TEST ---\n";
