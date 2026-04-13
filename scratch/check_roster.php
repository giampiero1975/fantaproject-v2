<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$seasonId = \App\Models\Season::where('season_year', 2023)->value('id');
$teamId = \App\Models\Team::where('name', 'like', '%Milan%')->value('id');

echo "Milan Team ID: $teamId\n";
echo "Season ID: $seasonId\n";

$rosterCount = \App\Models\PlayerSeasonRoster::where('team_id', $teamId)->where('season_id', $seasonId)->count();
echo "Giocatori nel roster Milan 2023: $rosterCount\n";

$pulisicInRoster = \App\Models\PlayerSeasonRoster::where('player_id', 830)
    ->where('season_id', $seasonId)
    ->first();

if ($pulisicInRoster) {
    echo "Pulisic (830) E' PRESENTE nel roster Milan 2023 (Team ID: " . $pulisicInRoster->team_id . ")\n";
} else {
    echo "Pulisic (830) NON E' PRESENTE nel roster Milan 2023.\n";
}
