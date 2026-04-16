<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 VERIFICA INTEGRITÀ DATI (Players <-> Roster)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// 1. Calciatori senza alcun record in roster
$orphans = Player::withTrashed()
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('player_season_roster')
              ->whereColumn('player_season_roster.player_id', 'players.id');
    })
    ->get(['id', 'name', 'fanta_platform_id']);

echo "1) Calciatori in 'players' SENZA alcun roster: " . $orphans->count() . "\n";
if ($orphans->count() > 0) {
    echo "   Esempi:\n";
    foreach ($orphans->take(5) as $p) {
        echo "   - [ID: {$p->id}] {$p->name} (FantaID: {$p->fanta_platform_id})\n";
    }
} else {
    echo "   ✅ Tutti i calciatori hanno almeno una stagione nel roster.\n";
}

echo "\n";

// 2. Roster che puntano a calciatori inesistenti
$dangling = DB::table('player_season_roster')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('players')
              ->whereColumn('players.id', 'player_season_roster.player_id');
    })
    ->select('player_id')
    ->distinct()
    ->get();

echo "2) Righe in 'player_season_roster' con 'player_id' INESISTENTE: " . $dangling->count() . "\n";
if ($dangling->count() > 0) {
    echo "   ⚠️ ATTENZIONE: Trovate " . $dangling->count() . " referenze rotte.\n";
    foreach ($dangling->take(5) as $d) {
        echo "   - PlayerID inesistente: {$d->player_id}\n";
    }
} else {
    echo "   ✅ Tutti i record del roster puntano a calciatori esistenti.\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
