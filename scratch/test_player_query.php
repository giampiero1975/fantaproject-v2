<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 TEST QUERY PLAYER RESOURCE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    DB::enableQueryLog();

    // Simulia getEloquentQuery() del Resource
    $query = Player::withTrashed()
        ->with(['latestRoster.team', 'rosters.season'])
        ->limit(10);

    echo "Esecuzione query base...\n";
    $players = $query->get();
    echo "✅ Query base completata (Trovati " . $players->count() . " record).\n";

    // Ora proviamo a simulare un ordinamento sulla squadra (che spesso causa il crash)
    echo "\nSimulazione ordinamento per team.name (via latestRoster)...\n";
    
    // Filament usa spesso subqueries per ordinare sulle relazioni hasOne
    $sortedQuery = Player::withTrashed()
        ->select('players.*')
        ->addSelect([
            'team_name' => \App\Models\PlayerSeasonRoster::select('teams.name')
                ->join('teams', 'teams.id', '=', 'player_season_roster.team_id')
                ->whereColumn('player_season_roster.player_id', 'players.id')
                ->oldest('season_id')
                ->limit(1)
        ])
        ->orderBy('team_name')
        ->limit(10);

    $results = $sortedQuery->get();
    echo "✅ Query ordinata completata.\n";

} catch (\Exception $e) {
    echo "❌ ERRORE RILEVATO: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
