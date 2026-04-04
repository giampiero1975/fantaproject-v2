<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

$currentSeason = Season::where('is_current', true)->first();
$teams = Team::whereHas('teamSeasons', function($q) use ($currentSeason) {
    $q->where('season_id', $currentSeason->id)->where('is_active', true);
})->get();

$years = [2021, 2022, 2023, 2024];

echo "=== Missing Standings for Active Teams ===\n";
foreach ($teams as $team) {
    $missing = [];
    foreach ($years as $year) {
        $exists = DB::table('team_historical_standings')
            ->where('team_id', $team->id)
            ->where('season_year', $year)
            ->exists();
        if (!$exists) {
            $missing[] = $year;
        }
    }
    if (!empty($missing)) {
        echo "Team: '" . $team->name . "' (ID: " . $team->id . ") missing years: " . implode(', ', $missing) . "\n";
        
        // Find if the team has ANY historical standings with a DIFFERENT ID?
        // Or if the FBref ID matched a DIFFERENT team?
        if ($team->fbref_id) {
             $otherTeamIds = DB::table('team_historical_standings')
                ->where('season_year', 2021)
                ->whereIn('team_id', function($q) use ($team) {
                    $q->select('id')->from('teams')->where('fbref_id', $team->fbref_id)->where('id', '<>', $team->id);
                })->get();
             if ($otherTeamIds->count() > 0) {
                 echo "  - Warning: Found standings for SAME FBref ID but DIFFERENT Team IDs!\n";
             }
        }
    }
}
