<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

$currentSeason = Season::where('is_current', true)->first();
$activeTeams = Team::whereHas('teamSeasons', function($q) use ($currentSeason) {
    $q->where('season_id', $currentSeason->id)->where('is_active', true);
})->get();

echo "Current Season ID: " . $currentSeason->id . "\n";
echo "Active Teams: " . $activeTeams->count() . "\n";

foreach ($activeTeams as $team) {
    $c2021 = DB::table('team_historical_standings')->where('team_id', $team->id)->where('season_year', 2021)->exists();
    $c2022 = DB::table('team_historical_standings')->where('team_id', $team->id)->where('season_year', 2022)->exists();
    $c2023 = DB::table('team_historical_standings')->where('team_id', $team->id)->where('season_year', 2023)->exists();
    $c2024 = DB::table('team_historical_standings')->where('team_id', $team->id)->where('season_year', 2024)->exists();
    
    if (!$c2021 || !$c2022 || !$c2023 || !$c2024) {
        echo "MISMATCH: " . $team->name . " (ID: $team->id) Missing: " 
            . (!$c2021 ? "2021 " : "") 
            . (!$c2022 ? "2022 " : "") 
            . (!$c2023 ? "2023 " : "") 
            . (!$c2024 ? "2024 " : "") 
            . "\n";
    }
}
