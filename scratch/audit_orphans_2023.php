<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;

echo "--- 🔍 ANALISI DISTRIBUZIONE ORFANI 2023 ---\n";

$s = Season::where('season_year', 2023)->first();
if (!$s) die("Season not found\n");

$orphans = PlayerSeasonRoster::where('season_id', $s->id)
    ->whereHas('player', function($q) {
        $q->whereNull('api_football_data_id');
    })
    ->with(['player', 'team'])
    ->get();

echo "Totale orfani rilevati: " . $orphans->count() . "\n\n";

echo "--- 📋 TOP 20 ORFANI ---\n";
foreach ($orphans->take(20) as $o) {
    echo "- {$o->player->name} ({$o->team->name})\n";
}

echo "\n--- 📊 DISTRIBUZIONE PER SQUADRA ---\n";
$dist = [];
foreach ($orphans as $o) {
    $teamName = $o->team->name;
    $dist[$teamName] = ($dist[$teamName] ?? 0) + 1;
}
arsort($dist);
foreach ($dist as $team => $count) {
    echo "  $team: $count\n";
}

echo "\n--- 🏁 FINE ANALISI ---\n";
