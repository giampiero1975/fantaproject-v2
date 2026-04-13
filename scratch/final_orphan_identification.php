<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;

echo "--- 🔍 IDENTIFICAZIONE FINALE ORFANI 2023 ---\n";

$s3 = Season::find(3);
$orphans = PlayerSeasonRoster::where('season_id', 3)
    ->whereHas('player', function($q) {
        $q->whereNull('api_football_data_id');
    })
    ->with(['player', 'team'])
    ->get();

echo "Orfani totali in Roster 2023: " . $orphans->count() . "\n";

foreach ($orphans as $o) {
    echo "  - Player: {$o->player->name} (ID: {$o->player->id}) | Team: {$o->team->name} (ID: {$o->team_id})\n";
}

echo "\n--- 🔍 RICERCA L4 CORRISPONDENTI (CREATI OGGI) ---\n";
$l4s = Player::whereNotNull('api_football_data_id')
    ->whereNull('fanta_platform_id')
    ->where('created_at', '>=', now()->startOfDay())
    ->get();

foreach ($orphans as $o) {
    foreach ($l4s as $l4) {
        similar_text(strtoupper($o->player->name), strtoupper($l4->name), $pct);
        if ($pct > 75) {
            echo "MATCH SOSPETTO: '{$o->player->name}' (Orfano) VS '{$l4->name}' (L4 API ID: {$l4->api_football_data_id}) | Score: ".round($pct,1)."%\n";
        }
    }
}

echo "--- 🏁 FINE ---\n";
