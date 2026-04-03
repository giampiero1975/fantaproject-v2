<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\Team;

$seasonsToCheck = [2021, 2022, 2023, 2024, 2025];
$report = [];

foreach ($seasonsToCheck as $year) {
    $season = Season::where('season_year', $year)->first();
    if (!$season) continue;
    
    $teams = $season->teams()->get();
    $missingFbref = $teams->whereNull('fbref_id');
    $missingApi = $teams->whereNull('api_id');
    
    $report[$year] = [
        'total' => $teams->count(),
        'missing_fbref' => $missingFbref->pluck('name')->toArray(),
        'missing_api' => $missingApi->pluck('name')->toArray(),
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT);
