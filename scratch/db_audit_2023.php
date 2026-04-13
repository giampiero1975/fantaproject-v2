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

$teams = Team::whereIn('name', ['Pisa', 'Milan', 'Inter', 'Lazio', 'Sassuolo'])
    ->orWhere('short_name', 'Pisa')
    ->get();

foreach ($teams as $team) {
    echo "SQUADRE: {$team->name} [ID: {$team->id}]\n";
    $ts = TeamSeason::where('team_id', $team->id)->where('season_id', $season->id)->get();
    
    if ($ts->isEmpty()) {
        echo "   ❌ Nessun record in team_seasons per il $year\n";
    } else {
        foreach ($ts as $entry) {
            $league = League::find($entry->league_id);
            echo "   ✅ Record team_seasons:\n";
            echo "      - League: " . ($league->name ?? 'NULL') . " [ID: {$entry->league_id}]\n";
            echo "      - Is Active: " . ($entry->is_active ? 'SI' : 'NO') . "\n";
        }
    }
    echo "-----------------------------------\n";
}

echo "\n--- LISTA LEGHE A DB ---\n";
foreach (League::all() as $l) {
    echo "ID: {$l->id} | Name: {$l->name} | API ID: {$l->api_id}\n";
}
