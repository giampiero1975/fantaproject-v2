<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\Team;

$seasonsToCheck = [2021, 2022, 2023, 2024, 2025];

foreach ($seasonsToCheck as $year) {
    $season = Season::where('season_year', $year)->first();
    if (!$season) {
        echo "Season $year not found\n";
        continue;
    }
    
    $totalTeams = $season->teams()->count();
    $withFbref = $season->teams()->whereNotNull('fbref_id')->count();
    $withApiId = $season->teams()->whereNotNull('api_id')->count();
    
    echo "Season $year: Total Teams: $totalTeams | With FBref ID: $withFbref | With API ID: $withApiId\n";
}
