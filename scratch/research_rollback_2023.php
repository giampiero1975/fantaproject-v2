<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerSeasonRoster;

echo "--- 🔍 RESEARCH ROLLBACK 2023 ---\n";

$s = Season::where('season_year', 2023)->first();
echo "Season 2023 ID: " . ($s?->id ?? 'NOT FOUND') . "\n";

$teamNames = ['AC Monza', 'Empoli FC', 'Frosinone Calcio', 'US Salernitana 1919'];
$teamIds = [];
foreach ($teamNames as $name) {
    $t = Team::where('name', 'LIKE', '%' . $name . '%')->first();
    if ($t) {
        $teamIds[] = $t->id;
        echo "Team $name: ID {$t->id}\n";
    } else {
        echo "Team $name: NOT FOUND\n";
    }
}

// Calciatori che appartengono ESCLUSIVAMENTE a questi team nel 2023 e NON sono mappati
$unmappedInOrphanTeams = Player::whereNull('api_football_data_id')
    ->whereHas('rosters', function($q) use ($s, $teamIds) {
        $q->where('season_id', $s->id)
          ->whereIn('team_id', $teamIds);
    })
    ->whereDoesntHave('rosters', function($q) use ($s, $teamIds) {
        $q->where('season_id', $s->id)
          ->whereNotIn('team_id', $teamIds);
    })
    ->count();

echo "Calciatori da 'spegnere' (esclusivi 403 e non mappati): $unmappedInOrphanTeams\n";

echo "--- 🏁 RESEARCH COMPLETATA ---\n";
