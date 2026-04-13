<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use App\Models\Team;

echo "--- 🛡️ REPORT DI INTEGRITÀ DATABASE ---\n\n";

// 1. Stagioni
echo "1. MAPPA STAGIONI:\n";
$seasons = Season::all();
foreach ($seasons as $s) {
    echo "   ID: {$s->id} | Anno: {$s->season_year} | Attuale: " . ($s->is_current ? "SI" : "NO") . "\n";
}

// 2. Anagrafica Master
echo "\n2. REGISTRO GIOCATORI:\n";
$total = Player::count();
$master = Player::whereNotNull('fanta_platform_id')->count();
$orphans = Player::whereNull('fanta_platform_id')->count();
echo "   Totale: $total\n";
echo "   Master (Listone): $master\n";
echo "   Orfani (L4): $orphans\n";

// 3. Roster
echo "\n3. DISTRIBUZIONE ROSTER STAGIONALI:\n";
$rosters = PlayerSeasonRoster::select('season_id', DB::raw('count(*) as total'))
    ->groupBy('season_id')
    ->get();

if ($rosters->isEmpty()) {
    echo "   ⚠️ ATTENZIONE: Nessun roster presente in tabella player_season_roster!\n";
} else {
    foreach ($rosters as $r) {
        $year = Season::find($r->season_id)?->season_year ?? "Unknown";
        echo "   Stagione ID {$r->season_id} ({$year}): {$r->total} record\n";
    }
}

// 4. Check Giocatori Critici
echo "\n4. CHECK CAMPIONI (Maignan):\n";
$maignan = Player::where('name', 'like', '%Maignan%')->first();
if ($maignan) {
    echo "   Trovato Maignan (ID: {$maignan->id})\n";
    $mRosters = PlayerSeasonRoster::where('player_id', $maignan->id)->count();
    echo "   Squadre associate (nelle varie stagioni): $mRosters\n";
} else {
    echo "   ⚠️ ATTENZIONE: Maignan NON TROVATO nell'anagrafica!\n";
}

echo "\n--- FINE REPORT ---\n";
