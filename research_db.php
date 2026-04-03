<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

echo "--- SPEZIA TEAMS ---\n";
Team::where('name', 'like', '%Spezia%')->get()->each(function($t) {
    echo "ID: {$t->id}, Name: {$t->name}, api_id: {$t->api_football_data_id}, fbref_id: {$t->fbref_id}\n";
});

echo "\n--- SEASONS ---\n";
Season::whereIn('year', [2021, 2022])->get()->each(function($s) {
    echo "ID: {$s->id}, Year: {$s->year}, Name: {$s->name}\n";
});

$speziaIds = Team::where('name', 'like', '%Spezia%')->pluck('id');

echo "\n--- TEAM_SEASON (Spezia) ---\n";
DB::table('team_season')->whereIn('team_id', $speziaIds)->get()->each(function($ts) {
    echo "Team ID: {$ts->team_id}, Season ID: {$ts->season_id}\n";
});

echo "\n--- TEAM_HISTORICAL_STANDINGS (Spezia) ---\n";
DB::table('team_historical_standings')->whereIn('team_id', $speziaIds)->get()->each(function($st) {
    echo "ID: {$st->id}, Team ID: {$st->team_id}, Season Year: {$st->season_year}, Points: {$st->points}\n";
});
