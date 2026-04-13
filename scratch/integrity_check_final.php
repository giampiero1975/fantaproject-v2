<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- 🛡️ VERIFICA INTEGRITÀ DATI POST-SYNC ---\n";

// 1. DUPLICATI (Stesso giocatore, stessa stagione, squadre diverse)
$dupes = DB::table('player_season_roster')
    ->select('player_id', 'season_id', DB::raw('COUNT(*) as count'))
    ->groupBy('player_id', 'season_id')
    ->having('count', '>', 1)
    ->get();

echo "🔍 [1] Verifica Duplicati (player_id, season_id):\n";
if ($dupes->isEmpty()) {
    echo "✅ Nessun duplicato trovato. Integrità garantita.\n";
} else {
    echo "❌ ATTENZIONE: Trovati " . $dupes->count() . " duplicati!\n";
    foreach ($dupes as $d) {
        echo "   - Player {$d->player_id} in Season {$d->season_id} ha {$d->count} record.\n";
    }
}

// 2. CONTEGGIO TOTALE PER STAGIONE
$counts = DB::table('player_season_roster')
    ->select('season_id', DB::raw('COUNT(*) as total'))
    ->groupBy('season_id')
    ->orderBy('season_id')
    ->get();

echo "\n📊 [2] Conteggio record per stagione:\n";
foreach ($counts as $c) {
    echo "   - Stagione ID {$c->season_id}: {$c->total} calciatori.\n";
}

// 3. VERIFICA CROSS-LINKING (Parent Team)
$loans = DB::table('player_season_roster')
    ->whereNotNull('parent_team_id')
    ->whereRaw('parent_team_id = team_id')
    ->count();

echo "\n🛡️ [3] Verifica Errori Logica Prestiti (Parent = Team):\n";
if ($loans == 0) {
    echo "✅ Nessun errore trovato. I prestiti puntano a squadre esterne.\n";
} else {
    echo "❌ ATTENZIONE: Trovati {$loans} record dove Parent Team coincide con il Team attuale.\n";
}

echo "\n--- 🏁 FINE VERIFICA ---";
