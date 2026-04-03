<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function show_table($name) {
    echo "--- TABLE: $name ---\n";
    try {
        foreach (DB::select("DESCRIBE $name") as $c) {
            echo "  - {$c->Field} ({$c->Type})\n";
        }
    } catch (\Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}

foreach (['teams', 'seasons', 'team_season', 'team_historical_standings'] as $t) {
    show_table($t);
}

echo "\n--- SPEZIA SEARCH ---\n";
$spezia = DB::table('teams')->where('name', 'like', '%Spezia%')->get();
foreach ($spezia as $s) {
    echo "ID: {$s->id}, Name: {$s->name}, fbref_id: " . ($s->fbref_id ?? 'NULL') . "\n";
}

echo "\n--- SEASONS SEARCH ---\n";
$seasons = DB::table('seasons')->whereIn('year', [2021, 2022])->get();
foreach ($seasons as $s) {
    echo "ID: {$s->id}, Year: {$s->year}, Name: {$s->name}\n";
}

echo "\n--- TEAM_SEASON FOR SPEZIA ---\n";
$speziaIds = $spezia->pluck('id');
$ts = DB::table('team_season')->whereIn('team_id', $speziaIds)->get();
foreach ($ts as $row) {
    echo "Team ID: {$row->team_id}, Season ID: {$row->season_id}\n";
}

echo "\n--- ORPHANS IN TEAM_SEASON ---\n";
$orphans = DB::table('team_season')
    ->leftJoin('teams', 'team_season.team_id', '=', 'teams.id')
    ->whereNull('teams.id')
    ->select('team_season.*')
    ->get();
echo "Found " . count($orphans) . " orphans.\n";
foreach ($orphans as $o) {
    echo "Team ID (Orphan): {$o->team_id}, Season ID: {$o->season_id}\n";
}

echo "\n--- STANDINGS FOR SPEZIA ---\n";
$standings = DB::table('team_historical_standings')->whereIn('team_id', $speziaIds)->get();
foreach ($standings as $st) {
    echo "Season Year: {$st->season_year}, Team ID: {$st->team_id}, Rank: {$st->rank}, Points: {$st->points}\n";
}
