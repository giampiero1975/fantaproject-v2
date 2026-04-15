<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;
use App\Models\Player;

echo "--- GHOST HUNT: TOP PLAYERS IN TRASH (SEASON 2025 ROSTER) ---\n";

$ghosts = PlayerSeasonRoster::where('season_id', 1)
    ->whereHas('player', fn($q) => $q->onlyTrashed())
    ->with(['player' => fn($q) => $q->onlyTrashed()])
    ->get();

echo "Totale 'Fantasmi' nel roster (Cestinati): " . $ghosts->count() . "\n\n";

echo "Top 30 Fantasmi rilevati:\n";
foreach ($ghosts->take(30) as $g) {
    echo " - ID: {$g->player->id} | Name: {$g->player->name} | FantaID: {$g->player->fanta_platform_id}\n";
}
