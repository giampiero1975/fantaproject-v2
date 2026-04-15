<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;

echo "--- MILAN ROSTER AUDIT (SEASON 2025) ---\n";

$roster = PlayerSeasonRoster::where('season_id', 1)
    ->where('team_id', 1) // Milan
    ->with('player')
    ->get();

foreach ($roster as $r) {
    echo "ID DB: {$r->player->id} | FantaID: {$r->player->fanta_platform_id} | Nome: {$r->player->name}\n";
}
