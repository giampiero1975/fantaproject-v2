<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;

echo "--- 🕵️ CACCIA AI 2 ORFANI INVISIBILI 2023 ---\n";

$s3Id = 3;

// 1. Tutti i record roster 2023
$allRosters = PlayerSeasonRoster::where('season_id', $s3Id)->pluck('player_id', 'id')->toArray();
echo "Totale records roster 2023: " . count($allRosters) . "\n";

// 2. Records con Player Mappato (Non eliminato e con API ID)
$mappedRosters = PlayerSeasonRoster::where('season_id', $s3Id)
    ->whereHas('player', function($q) {
        $q->whereNotNull('api_football_data_id');
    })->pluck('player_id', 'id')->toArray();
echo "Records mappati: " . count($mappedRosters) . "\n";

// 3. Differenza (I 2 orfani)
$missingIds = array_diff_key($allRosters, $mappedRosters);
echo "Differenza rilevata: " . count($missingIds) . " records.\n";

foreach ($missingIds as $rosterId => $playerId) {
    echo "\nANALISI ROSTER ID: $rosterId (Player ID: $playerId)\n";
    $roster = PlayerSeasonRoster::find($rosterId);
    $player = Player::withTrashed()->find($playerId);
    
    if ($player) {
        echo "  - Nome: {$player->name}\n";
        echo "  - API ID: " . ($player->api_football_data_id ?? 'NULL') . "\n";
        echo "  - Soft Deleted: " . ($player->deleted_at ? "SÌ ({$player->deleted_at})" : "NO") . "\n";
        echo "  - Team: " . ($roster->team ? $roster->team->name : "N/A") . "\n";
    } else {
        echo "  - PLAYER NON TROVATO NEL DB (Hard Deleted?)\n";
    }
}

echo "\n--- 🏁 FINE CACCIA ---\n";
