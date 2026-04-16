<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 RICERCA DUPLICATI ROSTER\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$dupes = DB::table('player_season_roster')
    ->select('player_id', 'season_id', DB::raw('COUNT(*) as count'))
    ->groupBy('player_id', 'season_id')
    ->having('count', '>', 1)
    ->get();

if ($dupes->isEmpty()) {
    echo "✅ Nessun duplicato trovato (player_id, season_id). I dati sono coerenti.\n";
} else {
    echo "⚠️ TROVATI " . $dupes->count() . " RECORD DUPLICATI!\n";
    foreach ($dupes as $d) {
        echo "- Player ID: {$d->player_id} | Season ID: {$d->season_id} | Count: {$d->count}\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
