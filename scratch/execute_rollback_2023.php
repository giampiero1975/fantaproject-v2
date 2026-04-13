<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "--- 🛠️ INIZIO ROLLBACK E PULIZIA 2023 ---\n";

$seasonId = 3; // 2023/24
$teamIdsToExclude = [13, 15, 16, 20]; // Empoli, Salernitana, Frosinone, Monza

// 1. Eliminazione Roster 2023
echo "[1/3] Eliminazione record roster Season 3...\n";
$deletedRosters = PlayerSeasonRoster::where('season_id', $seasonId)->delete();
echo "  - Rimossi $deletedRosters record.\n";

// 2. Soft-Delete Giocatori Esclusivi Team 403 (Orfani)
echo "[2/3] Soft-delete calciatori orfani esclusivi 403...\n";
$playersToSoftDelete = Player::whereNull('api_football_data_id')
    ->whereHas('rosters', function($q) use ($seasonId, $teamIdsToExclude) {
        $q->where('season_id', $seasonId)
          ->whereIn('team_id', $teamIdsToExclude);
    })
    ->whereDoesntHave('rosters', function($q) use ($seasonId, $teamIdsToExclude) {
        $q->where('season_id', $seasonId)
          ->whereNotIn('team_id', $teamIdsToExclude);
    })
    ->get();

foreach ($playersToSoftDelete as $p) {
    if (!$p->trashed()) {
        $p->delete();
        echo "  - [SOFT-DELETE] {$p->name} (Orfano inclusivo team 403)\n";
    }
}
echo "  - Operazione completata per " . $playersToSoftDelete->count() . " calciatori.\n";

// 3. Reset Log
echo "[3/3] Reset log RosterHistoricalSync.log...\n";
$logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
if (File::exists($logPath)) {
    File::put($logPath, '[' . date('Y-m-d H:i:s') . "] LOG RESET PER ROLLBACK 2023\n");
    echo "  - Log svuotato.\n";
} else {
    echo "  - File di log non trovato.\n";
}

echo "--- ✅ FINISH ROLLBACK & PULIZIA ---\n";
