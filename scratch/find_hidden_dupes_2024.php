<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;
use App\Models\Player;
use Illuminate\Support\Str;

echo "--- 🕵️ INVESTIGAZIONE L4 vs SOFT-DELETED (ROSTER 2024) ---\n";

// 1. Prendiamo tutti gli L4 della stagione 2024
$l4Rosters = PlayerSeasonRoster::where('season_id', 2)
    ->whereHas('player', fn($q) => $q->whereNull('fanta_platform_id'))
    ->with(['player', 'team'])
    ->get();

echo "🆕 Analisi di " . $l4Rosters->count() . " record L4...\n\n";

$found = 0;
foreach ($l4Rosters as $r) {
    if (!$r->player) continue;

    $apiName = $r->player->name;
    
    // Cerchiamo un match tra i cancellati (soft-deleted) nel registro
    // Usiamo una ricerca per nome parziale
    $parts = explode(' ', $apiName);
    $lastName = end($parts);

    $deletedMatch = Player::onlyTrashed()
        ->where('name', 'like', "%{$lastName}%")
        ->first();

    if ($deletedMatch) {
        $found++;
        echo "⚠️ [HIDDEN-MATCH #{$found}] '{$apiName}' (L4 ID: {$r->player->id})\n";
        echo "   -> Poteva matchare: '{$deletedMatch->name}' (ELIMINATO, ID: {$deletedMatch->id}, FantaID: {$deletedMatch->fanta_platform_id})\n";
        echo "   -> Team: {$r->team->short_name} | Ruolo: API({$r->role}) vs DB({$deletedMatch->role})\n";
        echo "\n";
    }
}

echo "--- 🏁 FINE (Trovati {$found} possibili recuperi) ---";
