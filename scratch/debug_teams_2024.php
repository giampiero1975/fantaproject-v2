<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeamSeason;
use App\Models\Season;

$year = 2024;
$season = Season::where('season_year', $year)->first();

if (!$season) {
    echo "Season $year not found\n";
    exit;
}

echo "Season ID: {$season->id}\n";

$teams = TeamSeason::with('team')
    ->where('season_id', $season->id)
    ->where('league_id', 1)
    ->get();

echo "Teams for Season $year, League ID 1:\n";
foreach ($teams as $ts) {
    echo "- {$ts->team->name} (ID: {$ts->team_id})\n";
}
