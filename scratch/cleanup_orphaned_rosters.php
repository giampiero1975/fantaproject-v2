<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧹 BONIFICA RECORD ORFANI ROSTER\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    DB::beginTransaction();

    // Identifichiamo gli orfani
    $orphansCount = DB::table('player_season_roster')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('players')
                  ->whereColumn('players.id', 'player_season_roster.player_id');
        })
        ->count();

    echo "Identificati: $orphansCount record orfani.\n";

    if ($orphansCount > 0) {
        // Pulizia
        $deleted = DB::table('player_season_roster')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('players')
                      ->whereColumn('players.id', 'player_season_roster.player_id');
            })
            ->delete();

        echo "✅ Eliminati con successo: $deleted record.\n";
    } else {
        echo "✅ Nessun orfano trovato. Il database è già pulito.\n";
    }

    DB::commit();
    echo "🎉 Transazione completata correttamente.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERRORE: " . $e->getMessage() . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
