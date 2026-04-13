<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\ImportLog;
use Illuminate\Support\Facades\File;

echo "--- AVVIO TABULA RASA STAGIONE 2023 ---\n";

// 1. Svuotamento Roster 2023
$rostersCount = PlayerSeasonRoster::where('season_id', 3)->delete();
echo "Rimossi $rostersCount record da player_season_roster.\n";

// 2. Eliminazione Calciatori L4 (creati oggi dopo le 08:00)
// Eliminiamo solo quelli che non hanno più roster associati (per evitare di toccare anagrafiche storiche valide)
$playersToDelete = Player::where('created_at', '>', '2026-04-12 08:00:00')->get();
$deletedPlayers = 0;
foreach ($playersToDelete as $p) {
    if ($p->rosters()->count() === 0) {
        $p->forceDelete();
        $deletedPlayers++;
    }
}
echo "Rimossi $deletedPlayers calciatori 'cloni' (L4).\n";

// 3. Reset Log su Database
ImportLog::where('details', 'like', '%Stagione 2023%')->delete();
echo "Log di importazione rimossi dal database.\n";

// 4. Svuotamento File Log fisico
$logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
if (File::exists($logPath)) {
    File::put($logPath, '');
    echo "File log RosterHistoricalSync.log svuotato.\n";
}

echo "--- RESET COMPLETATO ---\n";
