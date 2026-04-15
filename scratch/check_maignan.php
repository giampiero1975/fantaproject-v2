<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use App\Models\Player;
use App\Models\HistoricalPlayerStat;

$maignan = Player::where('name', 'like', '%Maignan%')->first();

if ($maignan) {
    echo "PLAYER: " . $maignan->name . "\n";
    echo "Fanta Platform ID (in players table): " . ($maignan->fanta_platform_id ?? 'NULL') . "\n";
    
    if ($maignan->fanta_platform_id) {
        $stats = HistoricalPlayerStat::where('player_fanta_platform_id', $maignan->fanta_platform_id)->get();
        echo "STATS COUNT for Player ID: " . $stats->count() . "\n";
        foreach ($stats as $s) {
            echo "- Season: {$s->season_year} | Team: {$s->team_name_for_season}\n";
        }
    }
} else {
    echo "Maignan not found in players table.\n";
}

echo "\n--- Global Stats Check ---\n";
$firstStat = HistoricalPlayerStat::first();
if ($firstStat) {
    echo "First Stat in DB has Fanta ID: " . $firstStat->player_fanta_platform_id . "\n";
    echo "Example Stat Player Name for Season: " . $firstStat->team_name_for_season . "\n";
} else {
    echo "historical_player_stats is EMPTY!\n";
}
