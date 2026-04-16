<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 ANALISI PROFONDA ORFANI E VINCOLI\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// 1. Verifica FOREIGN_KEY_CHECKS
$fkChecks = DB::select("SELECT @@FOREIGN_KEY_CHECKS as fk_checks")[0]->fk_checks;
echo "1) FOREIGN_KEY_CHECKS: " . ($fkChecks ? 'ATTIVATO' : 'DISATTIVATO') . "\n";

// 2. Mappatura orfani Roster
$rosterOrphans = DB::table('player_season_roster')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('players')
              ->whereColumn('players.id', 'player_season_roster.player_id');
    })
    ->get(['player_id', 'id']);

echo "2) Orfani in 'player_season_roster': " . $rosterOrphans->count() . "\n";

// 3. Mappatura orfani Stats
$statsOrphans = DB::table('historical_player_stats')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('players')
              ->whereColumn('players.id', 'historical_player_stats.player_id');
    })
    ->get(['player_id', 'id']);

echo "3) Orfani in 'historical_player_stats': " . $statsOrphans->count() . "\n";

if ($rosterOrphans->count() > 0 || $statsOrphans->count() > 0) {
    echo "\n⚠️ RILEVATA INCOERENZA DI INTEGRITÀ\n";
    echo "Il database riporta vincoli ON DELETE CASCADE attivi, ma i dati sono orfani.\n";
    echo "Questo suggerisce che i dati sono stati caricati/manipolati con FK Checks disattivati.\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
