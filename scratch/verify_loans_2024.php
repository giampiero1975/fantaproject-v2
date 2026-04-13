<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;

echo "--- 🛡️ VERIFICA PRESTITI 2024 ---\n";

$names = ['Bove', 'Cataldi'];

foreach ($names as $name) {
    $roster = PlayerSeasonRoster::whereHas('player', fn($q) => $q->where('name', 'like', "%$name%"))
        ->where('season_id', 2)
        ->with(['player', 'team', 'parentTeam'])
        ->first();

    if ($roster) {
        $owner = $roster->parentTeam?->short_name ?: 'Diretta';
        echo "⚽ {$roster->player->name}: {$roster->team->short_name} (Proprietà: {$owner})\n";
    } else {
        echo "❌ {$name} non trovato nel roster 2024.\n";
    }
}

echo "--- 🏁 FINE ---";
