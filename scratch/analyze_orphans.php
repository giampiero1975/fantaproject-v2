<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;

$orphans = PlayerSeasonRoster::whereDoesntHave('player')->get();
echo "Total orphans in player_season_roster: " . $orphans->count() . "\n";

$uniqueMissingPlayerIds = $orphans->pluck('player_id')->unique()->filter()->values();
echo "Total unique player_id missing: " . $uniqueMissingPlayerIds->count() . "\n";

$softDeletedCount = Player::onlyTrashed()->whereIn('id', $uniqueMissingPlayerIds)->count();
echo "Soft-deleted players found for these orphans: " . $softDeletedCount . "\n";

$completelyMissingCount = $uniqueMissingPlayerIds->count() - $softDeletedCount;
echo "Players completely missing from DB: " . $completelyMissingCount . "\n";

// Distribuzione per stagione
$bySeason = $orphans->groupBy('season_id')->map->count();
foreach ($bySeason as $seasonId => $count) {
    $year = \App\Models\Season::find($seasonId)->season_year ?? 'Unknown';
    echo "Season $year (ID $seasonId): $count orphans\n";
}

// Esempio di calciatori mancanti (ID)
if ($uniqueMissingPlayerIds->count() > 0) {
    echo "Sample Missing IDs: " . $uniqueMissingPlayerIds->take(10)->implode(', ') . "\n";
}
