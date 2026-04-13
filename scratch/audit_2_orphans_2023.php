<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;

echo "--- 🔍 AUDIT CHIRURGICO 2 ORFANI 2023 ---\n";

$s = Season::where('season_year', 2023)->first();
$teamIdsToExclude = [13, 15, 16, 20];

$orphans = PlayerSeasonRoster::where('season_id', $s->id)
    ->whereNotIn('team_id', $teamIdsToExclude)
    ->whereHas('player', function($q) {
        $q->whereNull('api_football_data_id');
    })
    ->with(['player', 'team'])
    ->get();

echo "Orfani trovati: " . $orphans->count() . "\n";

foreach ($orphans as $o) {
    echo "ID Roster: {$o->id} | Player: {$o->player->name} (ID: {$o->player->id}) | Team: {$o->team->name} (ID: {$o->team_id})\n";
    
    // Cerchiamo possibili L4 creati oggi con nome simile
    $l4Matches = Player::whereNotNull('api_football_data_id')
        ->whereNull('fanta_platform_id')
        ->where('created_at', '>=', now()->startOfDay())
        ->get();
        
    foreach ($l4Matches as $l4) {
        similar_text(strtoupper($o->player->name), strtoupper($l4->name), $pct);
        if ($pct > 70) {
            echo "  ⚠️ POSSIBILE L4 DUPLICATO: {$l4->name} (API ID: {$l4->api_football_data_id}) | Score: ".round($pct,1)."%\n";
        }
    }
}

echo "--- 🏁 FINE AUDIT ---\n";
