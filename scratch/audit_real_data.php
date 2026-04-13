<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Team;
use App\Models\Season;

echo "--- 🛠️ AUDIT DATI REALI (MYSQL) ---\n";

$totalPlayers = Player::count();
$totalRosters = PlayerSeasonRoster::count();

echo "Calciatori in Anagrafica: $totalPlayers\n";
echo "Record in Roster: $totalRosters\n";

// 1. Orfani di Calciatore
$orphanPlayers = PlayerSeasonRoster::whereDoesntHave('player')->count();
echo "Roster Orfani (Manca Calciatore): " . ($orphanPlayers ?: "0 ✅") . "\n";

// 2. Orfani di Squadra
$orphanTeams = PlayerSeasonRoster::whereDoesntHave('team')->count();
echo "Roster Orfani (Manca Squadra): " . ($orphanTeams ?: "0 ✅") . "\n";

// 3. Orfani di Stagione
$orphanSeasons = PlayerSeasonRoster::whereDoesntHave('season')->count();
echo "Roster Orfani (Manca Stagione): " . ($orphanSeasons ?: "0 ✅") . "\n";

// 4. Calciatori duplicati nel roster per la stessa stagione
$duplicates = PlayerSeasonRoster::select('player_id', 'season_id')
    ->groupBy('player_id', 'season_id')
    ->havingRaw('COUNT(*) > 1')
    ->count();
echo "Calciatori Duplicati stessa Stagione: " . ($duplicates ?: "0 ✅") . "\n";

echo "--- 🏁 AUDIT COMPLETATO ---\n";
