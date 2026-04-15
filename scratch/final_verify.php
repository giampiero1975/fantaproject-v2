<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\HistoricalPlayerStat;
use Illuminate\Support\Facades\DB;

echo "--- FINAL RELATIONAL AUDIT ---\n";

$stats = HistoricalPlayerStat::select('season_id', DB::raw('count(*) as total'))->groupBy('season_id')->get();
foreach ($stats as $s) {
    $seasonYear = \App\Models\Season::find($s->season_id)?->season_year;
    echo "Season ID: {$s->season_id} (Year: $seasonYear) | Total Records: {$s->total}\n";
}

$sample = HistoricalPlayerStat::with(['player', 'season', 'team'])->first();
if ($sample) {
    echo "\nSample Record Integrity Check:\n";
    echo " - Player: " . ($sample->player->name ?? 'ERROR') . " (ID: {$sample->player_id})\n";
    echo " - Season: " . ($sample->season->season_year ?? 'ERROR') . " (ID: {$sample->season_id})\n";
    echo " - Team ID: " . ($sample->team_id ?? 'NULL') . "\n";
}
