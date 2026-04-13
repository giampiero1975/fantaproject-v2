<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- 🔍 ESTRAZIONE CAMPIONE JSON (COMPETITION TEAMS) ---\n";

$apiKey = config('services.player_stats_api.providers.football_data_org.api_key');
$season = 2024;
$url = "https://api.football-data.org/v4/competitions/SA/teams?season={$season}";

$response = Http::withHeaders(['X-Auth-Token' => $apiKey])->get($url);

if ($response->successful()) {
    $data = $response->json();
    $teams = $data['teams'] ?? [];
    
    foreach ($teams as $t) {
        if ($t['id'] == 103) {
            echo "Team: {$t['name']}\n";
            echo "Esiste campo 'squad'? " . (isset($t['squad']) ? "SÌ" : "NO") . "\n";
            if (isset($t['squad']) && count($t['squad']) > 0) {
                echo "Esempio primo giocatore:\n";
                print_r(array_slice($t['squad'], 0, 1));
            }
            break;
        }
    }
} else {
    echo "Errore API: " . $response->status() . "\n";
}
