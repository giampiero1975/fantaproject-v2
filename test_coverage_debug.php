<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

$currentSeason = Season::where('is_current', true)->first();
if (!$currentSeason) {
    echo "No current season found.\n";
    exit;
}

$teams = Team::whereHas('teamSeasons', function($q) use ($currentSeason) {
    $q->where('season_id', $currentSeason->id)->where('is_active', true);
})->orderBy('name')->get();

echo "Current Season: " . $currentSeason->season_name . " (ID: " . $currentSeason->id . ")\n";
echo "Active Teams: " . $teams->count() . "\n\n";

echo str_pad("Team Name", 30) . "| 2021 | 2022 | 2023 | 2024\n";
echo str_repeat("-", 55) . "\n";

foreach ($teams as $team) {
    echo str_pad($team->name, 30) . "| ";
    foreach ([2021, 2022, 2023, 2024] as $year) {
        $exists = DB::table('team_historical_standings')
            ->where('team_id', $team->id)
            ->where('season_year', $year)
            ->exists();
        echo ($exists ? " OK  " : " --  ") . "| ";
    }
    echo "\n";
}
