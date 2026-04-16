<?php

use App\Models\PlayerSeasonRoster;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// La logica esatta del widget per i 199 mancanti
$seasonYear = 2021;
$season = \App\Models\Season::where('season_year', $seasonYear)->first();

if (!$season) {
    die("Stagione $seasonYear non trovata.");
}

$missingPlayers = PlayerSeasonRoster::query()
    ->join('players', 'players.id', '=', 'player_season_roster.player_id')
    ->join('team_season', function($join) {
        $join->on('player_season_roster.team_id', '=', 'team_season.team_id')
             ->on('player_season_roster.season_id', '=', 'team_season.season_id');
    })
    ->where('team_season.league_id', 1) 
    ->where('player_season_roster.season_id', $season->id)
    ->where(function($q) {
        $q->whereNull('players.deleted_at')
          ->orWhereNotNull('players.fanta_platform_id');
    })
    ->whereNull('players.fbref_id')
    ->select('players.id', 'players.name', 'players.deleted_at')
    ->distinct()
    ->get();

echo "🔍 DIAGNOSTICA CALCIATORI MANCANTI (Stagione $seasonYear)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Totale contati dal widget: " . $missingPlayers->count() . "\n";

$active = $missingPlayers->whereNull('deleted_at')->count();
$trashed = $missingPlayers->whereNotNull('deleted_at')->count();

echo "✅ Attivi: $active\n";
echo "❌ Ceduti (Trashed): $trashed\n";

echo "\nPrime 10 righe del report:\n";
foreach ($missingPlayers->take(10) as $p) {
    echo "- [ID: {$p->id}] {$p->name} (" . ($p->deleted_at ? 'CEDUTO' : 'ATTIVO') . ")\n";
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
