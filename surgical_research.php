<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Season;
use App\Models\Team;

echo "--- SEASONS (2021, 2022) ---\n";
Season::whereIn('year', [2021, 2022])->each(function($s) {
    echo "ID: {$s->id}, Year: {$s->year}, Name: {$s->name}\n";
});

echo "\n--- SPEZIA SEARCH ---\n";
Team::where('name', 'like', '%Spezia%')->each(function($t) {
    echo "ID: {$t->id}, Name: {$t->name}, api_id: {$t->api_football_data_id}, fbref_id: {$t->fbref_id}\n";
});

echo "\n--- TEAM_SEASON FOR SPEZIA ---\n";
DB::table('team_season')->whereIn('team_id', Team::where('name', 'like', '%Spezia%')->pluck('id'))->get()->each(function($row) {
    echo "Team ID: {$row->team_id}, Season ID: {$row->season_id}\n";
});

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

echo "\n--- STANDINGS FOR SPEZIA (if any) ---\n";
DB::table('team_historical_standings')->whereIn('team_id', Team::where('name', 'like', '%Spezia%')->pluck('id'))->get()->each(function($st) {
    echo "Season Year: {$st->season_year}, Team ID: {$st->team_id}, Rank: " . ($st->rank ?? 'N/A') . ", Points: " . ($st->points ?? 'N/A') . "\n";
});
