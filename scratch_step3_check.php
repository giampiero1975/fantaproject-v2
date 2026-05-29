<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== team_historical_standings ===" . PHP_EOL;
$rows = DB::table('team_historical_standings')
    ->join('teams', 'teams.id', '=', 'team_historical_standings.team_id')
    ->select('teams.name', 'team_historical_standings.season_year', 'team_historical_standings.position', 'team_historical_standings.points', 'team_historical_standings.data_source')
    ->orderBy('season_year')
    ->orderBy('position')
    ->get();

foreach ($rows as $r) {
    echo str_pad($r->season_year, 12) . str_pad($r->name, 28) . str_pad('Pos: ' . $r->position, 10) . str_pad('Pts: ' . $r->points, 12) . 'Src: ' . $r->data_source . PHP_EOL;
}
echo PHP_EOL . "Totale righe: " . $rows->count() . PHP_EOL;

echo PHP_EOL . "=== team_season (con posizione_finale) ===" . PHP_EOL;
$pivots = DB::table('team_season')
    ->join('teams', 'teams.id', '=', 'team_season.team_id')
    ->join('seasons', 'seasons.id', '=', 'team_season.season_id')
    ->select('teams.name', 'seasons.season_year', 'team_season.posizione_finale', 'team_season.punti')
    ->whereNotNull('team_season.posizione_finale')
    ->orderBy('seasons.season_year')
    ->get();

foreach ($pivots as $p) {
    echo str_pad($p->season_year, 12) . str_pad($p->name, 28) . 'Pos: ' . $p->posizione_finale . ' Pts: ' . $p->punti . PHP_EOL;
}
echo PHP_EOL . "Totale con posizione_finale: " . $pivots->count() . PHP_EOL;

echo PHP_EOL . "=== team_season totale (senza filtro) ===" . PHP_EOL;
echo "Totale righe in team_season: " . DB::table('team_season')->count() . PHP_EOL;
