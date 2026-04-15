<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use Illuminate\Support\Facades\DB;

echo "--- 🛠️ ALLINEAMENTO PROPRIETÀ REGISTRO (STAGIONE 2025/26) ---\n";

DB::transaction(function() {
    // 1. Reset di tutte le proprietà nel Registro Master
    // (Seguiamo la logica che il 2025 è l'unica verità attuale per le proprietà)
    $resetCount = Player::whereNotNull('parent_team_id')->update(['parent_team_id' => null]);
    echo "✅ Reset proprietà pregresse effettuato su {$resetCount} calciatori.\n";

    // 2. Recupero dei prestiti dal Roster 2025/26 (Season ID 1)
    $rosterLoans = PlayerSeasonRoster::where('season_id', 1)
        ->whereNotNull('parent_team_id')
        ->whereColumn('parent_team_id', '!=', 'team_id')
        ->get();

    echo "🔍 Recupero prestiti stagione 2025/26: trovati " . $rosterLoans->count() . " casi.\n";

    $updatedCount = 0;
    foreach ($rosterLoans as $rl) {
        $player = Player::find($rl->player_id);
        if ($player) {
            $player->update(['parent_team_id' => $rl->parent_team_id]);
            $updatedCount++;
            echo "   - ✅ [#{$player->id}] {$player->name} -> Proprietà Master: Team ID {$rl->parent_team_id}\n";
        }
    }

    echo "🚀 Allineamento completato: {$updatedCount} proprietà impostate nel Registro.\n";
});

echo "--- 🏁 FINE OPERAZIONE ---\n";
