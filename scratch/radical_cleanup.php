<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "--- 🚀 INIZIO BONIFICA RADICALE (Tabula Rasa) ---\n";

// 1. Pulizia Registro Players (Cloni L4)
echo "1. Rimozione cloni L4 (senza platform_id) creati oggi... ";
$clonesDeleted = Player::where('created_at', '>', now()->subDay())
    ->whereNull('fanta_platform_id')
    ->delete();
echo "FATTO ($clonesDeleted rimossi).\n";

// 2. Pulizia Roster 2023
echo "2. Svuotamento Roster Stagione 2023 (ID 3)... ";
$rostersDeleted = PlayerSeasonRoster::where('season_id', 3)->delete();
echo "FATTO ($rostersDeleted rimossi).\n";

// 3. Troncamento Log DB
echo "3. Svuotamento tabella import_logs... ";
DB::table('import_logs')->truncate();
echo "FATTO.\n";

// 4. Azzeramento Log Fisico
echo "4. Azzeramento RosterHistoricalSync.log... ";
$logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
if (File::exists($logPath)) {
    File::put($logPath, '');
    echo "FATTO.\n";
} else {
    echo "File non trovato (saltato).\n";
}

echo "\n--- ✅ VERIFICA FINALE --- \n";
$remainingClones = Player::where('created_at', '>', now()->subDay())->whereNull('fanta_platform_id')->count();
$remainingRosters = PlayerSeasonRoster::where('season_id', 3)->count();

echo "Cloni residui: $remainingClones (Target: 0)\n";
echo "Roster 2023 residui: $remainingRosters (Target: 0)\n";
echo "--------------------------------------------------\n";
