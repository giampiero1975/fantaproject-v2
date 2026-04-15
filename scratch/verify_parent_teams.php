<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;

echo "--- 🔍 VERIFICA COERENZA PARENT_TEAM_ID ---\n";

// 1. Prestiti identificati nei Roster (Proprietà != Squadra in cui gioca)
$loans = PlayerSeasonRoster::whereNotNull('parent_team_id')
    ->whereColumn('parent_team_id', '!=', 'team_id')
    ->count();

// 2. Proprietà impostate nel Registro (Players)
$registryPopulated = Player::whereNotNull('parent_team_id')->count();

// 3. Verifica campioni casuali per match tra Roster e Registry
$samples = PlayerSeasonRoster::whereNotNull('parent_team_id')
    ->whereColumn('parent_team_id', '!=', 'team_id')
    ->with(['team', 'parentTeam'])
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();

echo "1. Prestiti (Roster): {$loans}\n";
echo "2. Proprietà (Registry): {$registryPopulated}\n";
echo "------------------------------------------\n";

if ($samples->isEmpty()) {
    echo "ℹ️ Nessun prestito trovato con parent_team_id valorizzato.\n";
} else {
    echo "CAMPIONI DI VERIFICA (Roster vs Registro):\n";
    foreach ($samples as $s) {
        // Recuperiamo il player con withTrashed per sicurezza
        $player = Player::withTrashed()->find($s->player_id);
        if (!$player) {
            echo "- [ORFANO ID {$s->player_id}]: Presente nel Roster ma non nel Registro!\n";
            continue;
        }

        $regParent = $player->parent_team_id;
        $matchStatus = ($regParent == $s->parent_team_id) ? "✅ Coerente" : "⚠️ Registro Vuoto o Diverso (Reg: " . ($regParent ?? 'NULL') . ")";
        
        echo "- {$player->name}: Roster[{$s->team->short_name} < {$s->parentTeam->short_name}] | Reg[#{$regParent}] -> {$matchStatus}\n";
    }
}
echo "------------------------------------------\n";
