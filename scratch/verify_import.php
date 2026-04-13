<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Team;
use App\Models\Season;

echo "--- DATABASE INTEGRITY CHECK ---\n";

$playerCount = Player::count();
$rosterCount = PlayerSeasonRoster::count();
$teamCount   = Team::count();
$seasonCount = Season::count();

echo "Players: $playerCount\n";
echo "Rosters: $rosterCount\n";
echo "Teams:   $teamCount\n";
echo "Seasons: $seasonCount\n";

echo "\n--- SAMPLE DATA ---\n";
$player = Player::with(['rosters.team', 'rosters.season'])->first();
if ($player) {
    echo "Player: {$player->name} (ID: {$player->id})\n";
    foreach ($player->rosters as $r) {
        $seasonName = \App\Helpers\SeasonHelper::formatYear($r->season->season_year);
        echo "  - Season: $seasonName (ID: {$r->season_id})\n";
        echo "  - Team:   " . ($r->team?->name ?? 'UNKNOWN') . " (ID: {$r->team_id})\n";
        echo "  - Role:   {$r->role}\n";
    }
} else {
    echo "No players found.\n";
}

echo "\n--- INTEGRITY CHECKS ---\n";
$orphanedRosters = PlayerSeasonRoster::whereDoesntHave('player')->count();
$rostersWithoutTeam = PlayerSeasonRoster::whereDoesntHave('team')->count();
$rostersWithoutSeason = PlayerSeasonRoster::whereDoesntHave('season')->count();

echo "Orphaned Rosters (no player): $orphanedRosters\n";
echo "Rosters without Team:         $rostersWithoutTeam\n";
echo "Rosters without Season:       $rostersWithoutSeason\n";
