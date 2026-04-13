<?php

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- AVVIO BONIFICA CANONICA 2023 ---\n";

$seasonYear = 2023;
$season = Season::where('season_year', $seasonYear)->first();

if (!$season) {
    die("Errore: Stagione $seasonYear non trovata.\n");
}

DB::beginTransaction();

try {
    // 1. Identificazione eliminazione duplicati (creati dall'11 aprile 2026)
    $duplicates = Player::where('created_at', '>=', '2026-04-11')->get();
    $deletedCount = 0;
    foreach ($duplicates as $p) {
        $p->forceDelete();
        $deletedCount++;
    }
    echo "1. Eliminati $deletedCount calciatori duplicati (creati dopo il 2026-04-11).\n";

    // 2. Reset ID API per tutti i giocatori della stagione 2023
    $playerIds = PlayerSeasonRoster::where('season_id', $season->id)->pluck('player_id');
    $resetCount = Player::whereIn('id', $playerIds)->update(['api_football_data_id' => null]);
    echo "2. Resettati $resetCount ID API per i giocatori del roster $seasonYear.\n";

    // 3. Rimozione log di importazione corrotti
    $logsDeleted = ImportLog::where('season_id', $season->id)
        ->where('import_type', 'sync_rose_api_historical')
        ->delete();
    echo "3. Rimossi $logsDeleted log di sincronizzazione rose.\n";

    // 4. Pulizia file di log fisico
    $logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
    if (file_exists($logPath)) {
        file_put_contents($logPath, "");
        echo "4. File log RosterHistoricalSync.log svuotato.\n";
    }

    DB::commit();
    echo "--- BONIFICA COMPLETATA CON SUCCESSO ---\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "!!! ERRORE DURANTE LA BONIFICA: " . $e->getMessage() . "\n";
}
