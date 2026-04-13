<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TeamSeason;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

echo "--- INIZIO BONIFICA IS_ACTIVE ---\n";

// 1. Spegnimento Totale
echo "1. Spegnimento totale is_active... ";
$deactivated = TeamSeason::where('is_active', true)->update(['is_active' => false]);
echo "Fatto ($deactivated record disattivati).\n";

// 2. Identificazione stagione corrente ferrea
echo "2. Identificazione stagione corrente... ";
$currentSeason = Season::all()->filter(fn($s) => $s->isActuallyCurrent())->first();

if (!$currentSeason) {
    echo "ERRORE: Nessuna stagione risponde true a isActuallyCurrent()!\n";
    exit(1);
}
echo "Trovata: {$currentSeason->season_year} (ID: {$currentSeason->id})\n";

// 3. Riattivazione chirurgica (Serie A, ID 1)
echo "3. Riattivazione Serie A per la stagione corrente... ";
$activated = TeamSeason::where('season_id', $currentSeason->id)
    ->where('league_id', 1) // Serie A
    ->update(['is_active' => true]);
echo "Fatto ($activated team attivati).\n";

// 4. Report Finale
echo "\n--- QUALITY REPORT IS_ACTIVE ---\n";
$report = DB::table('team_season')
    ->join('seasons', 'team_season.season_id', '=', 'seasons.id')
    ->select('seasons.season_year', DB::raw('count(*) as active_teams'))
    ->where('team_season.is_active', true)
    ->groupBy('seasons.season_year')
    ->get();

if ($report->isEmpty()) {
    echo "ATTENZIONE: Nessun team attivo rilevato a DB.\n";
} else {
    foreach ($report as $row) {
        echo "Stagione {$row->season_year}: {$row->active_teams} team attivi\n";
    }
}

// Verifica totale 0 per il passato
$historicalActive = TeamSeason::where('is_active', true)
    ->where('season_id', '!=', $currentSeason->id)
    ->count();

echo "Team attivi nel passato (ERRORI): $historicalActive\n";
echo "--- FINE BONIFICA ---\n";
