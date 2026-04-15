<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\HistoricalPlayerStat;

try {
    echo "Attempting to create a test record...\n";
    $h = HistoricalPlayerStat::create([
        'player_fanta_platform_id' => 8888,
        'season_year' => 2021,
        'team_name_for_season' => 'TEST TEAM',
        'role_for_season' => 'P',
        'games_played' => 1,
    ]);
    
    echo "SUCCESS! Created ID: " . $h->id . "\n";
    echo "Count in DB now: " . HistoricalPlayerStat::count() . "\n";
} catch (\Exception $e) {
    echo "FAILURE: " . $e->getMessage() . "\n";
}
