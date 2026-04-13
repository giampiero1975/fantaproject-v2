<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;

echo "--- 🔍 AUDIT CHIRURGICO ORFANI 2023 (INCLUSI SOFT-DELETED) ---\n";

$s3 = Season::find(3);
$rosters = PlayerSeasonRoster::where('season_id', 3)->get();

$orphansCount = 0;
foreach ($rosters as $r) {
    // Cerchiamo il player associato, includendo i cancellati
    $p = Player::withTrashed()->find($r->player_id);
    
    if (!$p || is_null($p->api_football_data_id)) {
        $orphansCount++;
        echo "ORFANO TROVATO:\n";
        echo "  - Nome: " . ($p ? $p->name : "NON TROVATO") . "\n";
        echo "  - ID Player: " . $r->player_id . "\n";
        echo "  - Squadra: " . ($r->team ? $r->team->name : "N/A") . "\n";
        echo "  - Soft Deleted: " . (($p && $p->deleted_at) ? "Sì (" . $p->deleted_at . ")" : "No") . "\n";
        
        // Cerchiamo match in L4 creati oggi
        $l4s = Player::whereNotNull('api_football_data_id')
            ->whereNull('fanta_platform_id')
            ->where('created_at', '>=', now()->startOfDay())
            ->get();
            
        foreach ($l4s as $l4) {
            similar_text(strtoupper($p->name), strtoupper($l4->name), $pct);
            if ($pct > 75) {
                echo "    ⚠️ MATCH SOSPETTO CON L4: {$l4->name} (API ID: {$l4->api_football_data_id}) | Score: ".round($pct,1)."%\n";
            }
        }
        echo "\n";
    }
}

echo "Totale orfani (inclusi soft-deleted): $orphansCount\n";
echo "--- 🏁 FINE AUDIT ---\n";
