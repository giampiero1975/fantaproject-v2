<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- LISTING ALL TABLES ---\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $table) {
    echo array_values((array)$table)[0] . "\n";
}

echo "\n--- SPEZIA TEAMS ---\n";
$spezia = DB::table('teams')->where('name', 'like', '%Spezia%')->get();
foreach ($spezia as $s) {
    echo "ID: {$s->id}, Name: {$s->name}, fbref_id: " . ($s->fbref_id ?? 'NULL') . "\n";
}

echo "\n--- SEASONS (2021, 2022) ---\n";
$seasons = DB::table('seasons')->whereIn('year', [2021, 2022])->get();
foreach ($seasons as $s) {
    echo "ID: {$s->id}, Year: {$s->year}, Name: {$s->name}\n";
}

echo "\n--- ORPHANS IN team_season ---\n";
$orphans = DB::select("SELECT ts.* FROM team_season ts LEFT JOIN teams t ON ts.team_id = t.id WHERE t.id IS NULL");
echo "Found " . count($orphans) . " orphans.\n";
foreach ($orphans as $o) {
    echo "Team ID (Orphan): {$o->team_id}, Season ID: {$o->season_id}\n";
}

echo "\n--- DESCRIBE team_historical_standings ---\n";
$cols = DB::select("DESCRIBE team_historical_standings");
foreach ($cols as $c) {
    echo "- {$c->Field} ({$c->Type})\n";
}
