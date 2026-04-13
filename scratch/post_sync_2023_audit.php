<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "--- 🛠️ AUDIT POST-SYNC 2023/24 (DATI REALI) ---\n";

$s = Season::where('season_year', 2023)->first();
if (!$s) {
    echo "❌ Errore: Stagione 2023 non trovata.\n";
    exit(1);
}

// 1. Audit Duplicati L4
echo "\n🔍 [1] ANALISI DUPLICATI L4 (API vs Listone)\n";
$l4Players = Player::whereNotNull('api_football_data_id')
    ->whereNull('fanta_platform_id')
    ->get();

$suspicious = 0;
foreach ($l4Players as $l4) {
    $matches = Player::whereNotNull('fanta_platform_id')
        ->where('id', '!=', $l4->id)
        ->get();

    foreach ($matches as $match) {
        similar_text(strtoupper($l4->name), strtoupper($match->name), $pct);
        if ($pct > 85) {
            $suspicious++;
            echo "⚠️  Sospetto Duplicato: '{$l4->name}' (API L4) vs '{$match->name}' (Listone) | Score: ".round($pct,1)."%\n";
        }
    }
}
echo "Totale casi sospetti trovati: " . ($suspicious ?: "0 ✅") . "\n";

// 2. Check Orfani Roster 2023
echo "\n🔍 [2] VERIFICA RECORD ORFANI (Roster 2023)\n";
$orphans = PlayerSeasonRoster::where('season_id', $s->id)
    ->whereDoesntHave('player')
    ->count();
echo "Record Roster 2023 senza Player: " . ($orphans ?: "0 ✅") . "\n";

// 3. Consistenza ID API
echo "\n🔍 [3] CONSISTENZA ID API (Multi-Player Mapping)\n";
$multiMapped = DB::table('players')
    ->select('api_football_data_id', DB::raw('COUNT(*) as count'))
    ->whereNotNull('api_football_data_id')
    ->groupBy('api_football_data_id')
    ->having('count', '>', 1)
    ->get();

if ($multiMapped->isEmpty()) {
    echo "Tutti gli ID API sono mappati univocamente. ✅\n";
} else {
    echo "❌ ATTENZIONE: Trovati ID API duplicati!\n";
    foreach ($multiMapped as $m) {
        $playerNames = Player::where('api_football_data_id', $m->api_football_data_id)->pluck('name')->toArray();
        echo " - ID {$m->api_football_data_id}: " . implode(', ', $playerNames) . "\n";
    }
}

// 4. Statistiche di Copertura 2023
echo "\n📊 [4] STATISTICHE DI COPERTURA 2023/24\n";
$totalRoster = PlayerSeasonRoster::where('season_id', $s->id)->count();
$covered = PlayerSeasonRoster::where('season_id', $s->id)
    ->whereHas('player', function($q) {
        $q->whereNotNull('api_football_data_id');
    })->count();
$uncovered = $totalRoster - $covered;

echo "Totale Giocatori in Roster: $totalRoster\n";
echo "Mappati con ID API: $covered (".round(($covered/$totalRoster)*100, 1)."%)\n";
echo "Senza ID API (Scoperti): $uncovered (".round(($uncovered/$totalRoster)*100, 1)."%)\n";

echo "\n--- 🏁 AUDIT COMPLETATO ---\n";
