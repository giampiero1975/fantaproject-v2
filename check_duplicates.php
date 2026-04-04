<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use Illuminate\Support\Facades\DB;

$teams = Team::all();
$seenNames = [];
$duplicates = [];

foreach ($teams as $team) {
    $normalized = strtolower(str_replace([' ', '-', '.'], '', $team->name));
    if (isset($seenNames[$normalized])) {
        $duplicates[] = [
            'normalized' => $normalized,
            'team1' => $seenNames[$normalized],
            'team2' => $team->toArray(),
        ];
    } else {
        $seenNames[$normalized] = $team->toArray();
    }
}

if (!empty($duplicates)) {
    echo "=== Duplicate Teams Found ===\n";
    foreach ($duplicates as $dup) {
        echo "Match: " . $dup['normalized'] . "\n";
        echo "  - Team 1 ID: " . $dup['team1']['id'] . " | Name: " . $dup['team1']['name'] . " | API ID: " . $dup['team1']['api_id'] . " | FBref ID: " . $dup['team1']['fbref_id'] . "\n";
        echo "  - Team 2 ID: " . $dup['team2']['id'] . " | Name: " . $dup['team2']['name'] . " | API ID: " . $dup['team2']['api_id'] . " | FBref ID: " . $dup['team2']['fbref_id'] . "\n";
        
        // Check which one has standings
        $s1 = DB::table('team_historical_standings')->where('team_id', $dup['team1']['id'])->count();
        $s2 = DB::table('team_historical_standings')->where('team_id', $dup['team2']['id'])->count();
        echo "  - Standings: T1: $s1 | T2: $s2\n";
        
        // Check which one is in current season
        $c1 = DB::table('team_season')->where('team_id', $dup['team1']['id'])->where('season_id', 1)->count();
        $c2 = DB::table('team_season')->where('team_id', $dup['team2']['id'])->where('season_id', 1)->count();
        echo "  - Current Season: T1: $c1 | T2: $c2\n";
        echo "---------------------------\n";
    }
} else {
    echo "No obvious duplicates found by normalized name.\n";
}
