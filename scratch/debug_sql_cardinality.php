<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 SQL DEBUGGER: PLAYER RESOURCE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

DB::listen(function ($query) {
    if (str_contains($query->sql, 'players')) {
        echo "SQL: " . $query->sql . "\n";
        echo "BINDINGS: " . json_encode($query->bindings) . "\n\n";
    }
});

try {
    echo "--- Test 1: Query base (con filtri popup) ---\n";
    // Simuliamo i filtri passati nell'iframe:
    // season_id = 1 (2021?), fbref_id IS NULL, trashed = with
    Player::withTrashed()
        ->with(['latestRoster.team', 'rosters.season'])
        ->whereHas('rosters', function ($q) {
             // Simuliamo il filtro stagione se necessario
        })
        ->whereNull('fbref_id')
        ->limit(5)
        ->get();

    echo "--- Test 2: Simulazione SORT su latestRoster.team.name ---\n";
    // Filament simula il sort sulle relazioni hasOne/belongsTo tramite subquery o join
    // Proviamo a riprodurre il pattern tipico di Filament per le relazioni nested
    $query = Player::withTrashed()
        ->addSelect([
            'team_name_sort' => \App\Models\Team::select('name')
                ->whereIn('id', function($sub) {
                    $sub->select('team_id')
                        ->from('player_season_roster')
                        ->whereColumn('player_id', 'players.id')
                        ->orderBy('season_id', 'asc')
                        ->limit(1);
                })
                ->limit(1)
        ])
        ->orderBy('team_name_sort')
        ->limit(5);

    $query->get();

} catch (\Exception $e) {
    echo "❌ CRASH RILEVATO!\n";
    echo "Errore: " . $e->getMessage() . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
