<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use Illuminate\Support\Facades\DB;

echo "--- 🛠️ AUDIT INCROCIATO 2021 VS 2022 ---\n";

// 1. Recupero ID Stagioni
$s2021 = Season::where('season_year', 2021)->first();
$s2022 = Season::where('season_year', 2022)->first();

if (!$s2021 || !$s2022) {
    die("Errore: Stagioni non trovate correttamente.\n");
}

echo "Season 2021 ID: {$s2021->id}\n";
echo "Season 2022 ID: {$s2022->id}\n";

// 2. Query Trasferimenti (Stesso player, team diverso tra 2021 e 2022)
$transfers = DB::table('player_season_roster as psr21')
    ->join('player_season_roster as psr22', 'psr21.player_id', '=', 'psr22.player_id')
    ->join('players as p', 'p.id', '=', 'psr21.player_id')
    ->join('teams as t21', 't21.id', '=', 'psr21.team_id')
    ->join('teams as t22', 't22.id', '=', 'psr22.team_id')
    ->where('psr21.season_id', $s2021->id)
    ->where('psr22.season_id', $s2022->id)
    ->where('psr21.team_id', '!=', 'psr22.team_id')
    ->select('p.name', 't21.name as team_21', 't22.name as team_22')
    ->get();

echo "Trasferimenti Rilevati a DB: " . $transfers->count() . "\n";

if ($transfers->count() > 0) {
    echo "Primi 5 Trasferimenti:\n";
    foreach ($transfers->take(5) as $t) {
        echo "- {$t->name}: {$t->team_21} -> {$t->team_22}\n";
    }
}

// 3. Verifica Campione: Dybala
echo "\n🔍 VERIFICA CAMPIONE: DYBALA\n";
$dybala = Player::where('name', 'LIKE', '%Dybala%')->first();
if ($dybala) {
    echo "Player: {$dybala->name} (ID: {$dybala->id})\n";
    $rosters = PlayerSeasonRoster::where('player_id', $dybala->id)
        ->with('team', 'season')
        ->orderBy('season_id')
        ->get();
    
    foreach ($rosters as $r) {
        echo " - Stagione: {$r->season->season_year} | Team: {$r->team->name} | Quot: {$r->initial_quotation}\n";
    }
} else {
    echo "⚠️ Dybala non trovato nel database.\n";
}

echo "--- 🏁 AUDIT COMPLETATO ---\n";
