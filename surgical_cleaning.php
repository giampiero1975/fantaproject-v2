<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- START SURGICAL CLEANING ---\n";

// 1. Clean orphans
$count = DB::table('team_season')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('teams')
              ->whereColumn('teams.id', 'team_season.team_id');
    })
    ->delete();
echo "Deleted $count orphan records in team_season.\n";

// 2. Unify Spezia (ID 48 is Master)
$masterId = 48;
$duplicates = DB::table('teams')
    ->where('name', 'like', '%Spezia%')
    ->where('id', '!=', $masterId)
    ->pluck('id');

if ($duplicates->isNotEmpty()) {
    echo "Found duplicate Spezia IDs: " . implode(', ', $duplicates->toArray()) . "\n";
    
    // Update team_season to master
    $tsCount = DB::table('team_season')
        ->whereIn('team_id', $duplicates)
        ->update(['team_id' => $masterId]);
    echo "Updated $tsCount relations in team_season to Master ID $masterId.\n";
    
    // Update team_historical_standings to master
    $thCount = DB::table('team_historical_standings')
        ->whereIn('team_id', $duplicates)
        ->update(['team_id' => $masterId]);
    echo "Updated $thCount relations in team_historical_standings to Master ID $masterId.\n";
    
    // Delete duplicates from teams
    $delCount = DB::table('teams')->whereIn('id', $duplicates)->delete();
    echo "Deleted $delCount duplicate team records.\n";
} else {
    echo "No duplicate Spezia records found.\n";
}

// 3. Inject missing standings for Spezia 2022 (Season ID 4, Year 2022)
$seasonYear = '2022'; // Migration says string

$exists = DB::table('team_historical_standings')
    ->where('team_id', $masterId)
    ->where('season_year', $seasonYear)
    ->exists();

if (!$exists) {
    echo "Injecting Spezia 2022 standings...\n";
    DB::table('team_historical_standings')->insert([
        'team_id' => $masterId,
        'season_year' => $seasonYear,
        'league_name' => 'Serie A',
        'position' => 18,
        'points' => 31,
        'played_games' => 38,
        'won' => 6,
        'draw' => 13,
        'lost' => 19,
        'goals_for' => 31,
        'goals_against' => 62,
        'goal_difference' => -31,
        'data_source' => 'manual_fix',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Spezia 2022 injected successfully.\n";
} else {
    echo "Spezia 2022 standings already present. Updating to correct values...\n";
    DB::table('team_historical_standings')
        ->where('team_id', $masterId)
        ->where('season_year', $seasonYear)
        ->update([
            'league_name' => 'Serie A',
            'position' => 18,
            'points' => 31,
            'played_games' => 38,
            'won' => 6,
            'draw' => 13,
            'lost' => 19,
            'goals_for' => 31,
            'goals_against' => 62,
            'goal_difference' => -31,
            'updated_at' => now(),
        ]);
    echo "Spezia 2022 updated successfully.\n";
}

echo "--- CLEANING COMPLETED ---\n";
