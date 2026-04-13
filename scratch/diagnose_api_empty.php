<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use Illuminate\Support\Facades\Http;

echo "--- 🔍 DIAGNOSTICA API ROSTER VUOTI ---\n";

$apiKey = config('services.player_stats_api.providers.football_data_org.api_key');
if (!$apiKey) die("API KEY MANCANTE\n");

// Team di test: Bologna (103) - Stagione 2024
$apiTeamId = 103;
$season = 2024;
$url = "https://api.football-data.org/v4/teams/{$apiTeamId}?season={$season}";

echo "Chiamata a: $url\n";

$response = Http::withHeaders(['X-Auth-Token' => $apiKey])->get($url);

echo "Status: " . $response->status() . "\n";
if ($response->successful()) {
    $data = $response->json();
    $squadCount = isset($data['squad']) ? count($data['squad']) : 'MANCANTE';
    echo "Numero Giocatori in Squadra: $squadCount\n";
    if ($squadCount === 0) {
        echo "⚠️  L'API restituisce un array 'squad' VUOTO per questa stagione.\n";
    }
} else {
    echo "Errore API: " . $response->body() . "\n";
}

echo "--- 🏁 FINE DIAGNOSTICA ---\n";
