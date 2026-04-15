<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Player;
use App\Models\HistoricalPlayerStat;
use App\Models\PlayerSeasonRoster;

echo "--- CORE AUDIT --- \n";

// 1. Check if ID 4312 exists in players
$maignan = Player::where('fanta_platform_id', 4312)->first();
if ($maignan) {
    echo "Found Maignan (4312) in players table. ID: {$maignan->id}\n";
    $roster = PlayerSeasonRoster::where('player_id', $maignan->id)->where('season_id', 1)->first();
    echo "Is in Season 1 Roster? " . ($roster ? 'YES' : 'NO') . "\n";
} else {
    echo "Fanta ID 4312 NOT FOUND in players table!\n";
    // Search by name fallback to see if ID is different
    $maignanByName = Player::where('name', 'like', '%Maignan%')->first();
    if ($maignanByName) {
        echo "Found Maignan by name. His actual Fanta ID in V2 is: " . $maignanByName->fanta_platform_id . "\n";
    }
}

// 2. Check how many of the 525 roster players have stats
$activePlayers = Player::whereHas('rosters', fn($q) => $q->where('season_id', 1))->get();
$withStats = 0;
foreach ($activePlayers as $p) {
    if (HistoricalPlayerStat::where('player_fanta_platform_id', $p->fanta_platform_id)->exists()) {
        $withStats++;
    }
}

echo "Active Players in Roster S1: " . $activePlayers->count() . "\n";
echo "Active Players WITH matching stats: " . $withStats . "\n";

// 3. Sample of missed players
$missed = 0;
echo "\nExample of active players WITHOUT stats:\n";
foreach ($activePlayers as $p) {
    if (!HistoricalPlayerStat::where('player_fanta_platform_id', $p->fanta_platform_id)->exists()) {
        echo "- {$p->name} (Fanta ID: {$p->fanta_platform_id})\n";
        $missed++;
        if ($missed >= 10) break;
    }
}
