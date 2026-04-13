<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;

echo "--- 📊 QUERY DELLA VERITÀ: COPERTURA API GLOBALE ---\n";
echo str_repeat("━", 60) . "\n";
echo sprintf("%-10s | %-12s | %-12s | %-10s\n", "STAGIONE", "TOT LISTONE", "MAPPATI API", "COPERTURA");
echo str_repeat("─", 60) . "\n";

foreach (Season::orderBy('season_year')->get() as $s) {
    if ($s->season_year < 2021) continue;

    $total = PlayerSeasonRoster::where('season_id', $s->id)->count();
    if ($total === 0) continue;

    $mapped = PlayerSeasonRoster::where('season_id', $s->id)
        ->whereHas('player', function($q) {
            $q->whereNotNull('api_football_data_id');
        })->count();

    $percent = ($total > 0) ? round(($mapped / $total) * 100, 1) : 0;

    echo sprintf("%-10s | %-12d | %-12d | %-10s\n", 
        $s->season_year, 
        $total, 
        $mapped, 
        "$percent%"
    );
}

echo str_repeat("━", 60) . "\n";

// Dettaglio orfani 2023 (exclude 403 teams)
$s2023 = Season::where('season_year', 2023)->first();
$teamIdsToExclude = [13, 15, 16, 20];
$orphans2023 = PlayerSeasonRoster::where('season_id', $s2023?->id)
    ->whereIn('team_id', $teamIdsToExclude)
    ->whereHas('player', function($q) {
        $q->whereNull('api_football_data_id');
    })->count();

echo "Nota: Nella stagione 2023, ci sono $orphans2023 calciatori orfani appartenenti ai team in 403 (esclusi dal sync).\n";

echo "--- 🏁 FINE REPORT ---\n";
