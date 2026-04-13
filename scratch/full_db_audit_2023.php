<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Season;
use App\Models\TeamSeason;
use App\Models\League;

$year = 2023;
$season = Season::where('season_year', $year)->first();

echo "--- ANALISI DATABASE STAGIONE $year ---\n";
echo "Season ID: " . ($season->id ?? 'NON TROVATA') . "\n\n";

// Prendiamo un campione di squadre per vedere come sono mappate
$teams = Team::whereIn('name', ['Milan', 'Inter', 'Pisa', 'Sassuolo', 'Lazio', 'Frosinone'])->get();

foreach ($teams as $team) {
    echo "TEAM: {$team->name} [ID: {$team->id}]\n";
    $ts = TeamSeason::where('team_id', $team->id)->where('season_id', $season->id)->get();
    
    if ($ts->isEmpty()) {
        echo "   ❌ Nessun record in team_seasons per il $year\n";
    } else {
        foreach ($ts as $entry) {
            $league = League::find($entry->league_id);
            echo "   ✅ Lega: " . ($league->name ?? 'NULL') . " [ID: {$entry->league_id}]\n";
            echo "   ✅ Is Active: " . ($entry->is_active ? 'SI' : 'NO') . "\n";
        }
    }
    echo "-----------------------------------\n";
}

echo "\n--- STATISTICHE TOTALI STAGIONE $year ---\n";
$allTs = TeamSeason::where('season_id', $season->id)->get();
echo "Totale team in team_seasons: " . $allTs->count() . "\n";
echo "Team Active: " . $allTs->where('is_active', true)->count() . "\n";
echo "Per Lega:\n";
foreach ($allTs->groupBy('league_id') as $leagueId => $entries) {
    $l = League::find($leagueId);
    echo "   - " . ($l->name ?? 'Sconosciuta') . " (ID $leagueId): " . $entries->count() . " team (" . $entries->where('is_active', true)->count() . " active)\n";
}
