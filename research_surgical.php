<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function dump_table($name, $query = null) {
    echo "--- TABLE: $name ---\n";
    $results = $query ? $query->get() : DB::table($name)->get();
    foreach ($results as $row) {
        print_r($row);
    }
}

echo "--- SPEZIA TEAMS ---\n";
$speziaTeams = DB::table('teams')->where('name', 'like', '%Spezia%')->get();
foreach ($speziaTeams as $t) {
    echo "ID: {$t->id}, Name: {$t->name}, api_id: {$t->api_football_data_id}, fbref_id: {$t->fbref_id}\n";
}

echo "\n--- SEASONS (2021, 2022) ---\n";
$seasons = DB::table('seasons')->whereIn('year', [2021, 2022])->get();
foreach ($seasons as $s) {
    echo "ID: {$s->id}, Year: {$s->year}, Name: {$s->name}\n";
}

$speziaIds = $speziaTeams->pluck('id');
$seasonIds = $seasons->pluck('id');

echo "\n--- TEAM_SEASON CONNECTIONS ---\n";
$teamSeasons = DB::table('team_season')->whereIn('team_id', $speziaIds)->get();
foreach ($teamSeasons as $ts) {
    echo "Team ID: {$ts->team_id}, Season ID: {$ts->season_id}\n";
}

echo "\n--- TEAM_HISTORICAL_STANDINGS (Spezia) ---\n";
$standings = DB::table('team_historical_standings')->whereIn('team_id', $speziaIds)->get();
foreach ($standings as $st) {
    print_r($st);
}

echo "\n--- ORPHAN SEARCH (team_season without valid team) ---\n";
$orphans = DB::table('team_season')
    ->leftJoin('teams', 'team_season.team_id', '=', 'teams.id')
    ->whereNull('teams.id')
    ->select('team_season.*')
    ->get();
echo "Found " . count($orphans) . " orphans.\n";
foreach ($orphans as $o) {
    print_r($o);
}
