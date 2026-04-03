<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

$totalTeams = Team::count();
$withFbref = Team::whereNotNull('fbref_id')->where('fbref_id', '!=', '')->count();

echo "--- ANALISI FBREF IDs (MASTER TEAMS) ---\n";
echo "Totale Squadre in DB: $totalTeams\n";
echo "Squadre con fbref_id mappato: $withFbref (" . round(($withFbref / max(1, $totalTeams)) * 100, 1) . "%)\n\n";

if ($withFbref > 0) {
    echo "Esempi di mappature esistenti:\n";
    $examples = Team::whereNotNull('fbref_id')->where('fbref_id', '!=', '')->limit(10)->get();
    foreach ($examples as $team) {
        echo "- {$team->name} (API ID: " . ($team->api_id ?: 'NULL') . ") -> FBref ID: {$team->fbref_id}\n";
    }
}

echo "\n--- ANALISI COPERTURA PER STAGIONE (2021-2025) ---\n";
$seasons = [2021, 2022, 2023, 2024, 2025];
foreach ($seasons as $year) {
    $season = Season::where('season_year', $year)->first();
    if (!$season) continue;
    
    $teamsInSeason = $season->teams()->count();
    $mappedInSeason = $season->teams()->whereNotNull('fbref_id')->where('fbref_id', '!=', '')->count();
    $status = ($teamsInSeason === $mappedInSeason && $teamsInSeason > 0) ? "✅ OK" : "⚠️ GAP (" . ($teamsInSeason - $mappedInSeason) . " mancati)";
    
    echo "[$year] Squadre: $teamsInSeason | FBref: $mappedInSeason | Status: $status\n";
    
    if ($teamsInSeason > $mappedInSeason) {
        $missing = $season->teams()
            ->where(function($q) { $q->whereNull('fbref_id')->orWhere('fbref_id', ''); })
            ->pluck('name')->toArray();
        echo "   Mancanti: " . implode(', ', $missing) . "\n";
    }
}

