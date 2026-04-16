<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\PlayerSeasonRoster;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$teamId = 11; // Napoli
$seasonYear = 2021;
$season = \App\Models\Season::where('season_year', $seasonYear)->first();

echo "🔍 STATUS COPERTURA NAPOLI ($seasonYear)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Usiamo withTrashed() caricandolo manualmente o tramite la relazione se configurata
$roster = PlayerSeasonRoster::where('team_id', $teamId)
    ->where('season_id', $season->id)
    ->get();

$mapped = 0;
$missing = [];

foreach ($roster as $r) {
    // Carichiamo il player includendo i cancellati
    $player = Player::withTrashed()->find($r->player_id);
    
    if (!$player) {
        echo "⚠️ Roster ID {$r->id} punta a un player inesistente fisico (ID: {$r->player_id})\n";
        continue;
    }

    if ($player->fbref_id) {
        $mapped++;
    } else {
        $missing[] = $player->name . " (ID: " . $player->id . ") [" . ($player->trashed() ? 'CEDUTO' : 'ATTIVO') . "]";
    }
}

echo "\n📊 RIEPILOGO:\n";
echo "✅ Mappati: $mapped\n";
echo "❌ Mancanti: " . count($missing) . "\n";

if (count($missing) > 0) {
    echo "\nEsempi Mancanti (Mancanza di FBref ID):\n";
    foreach (array_slice($missing, 0, 15) as $m) {
        if (str_contains($m, 'Koulibaly')) {
            echo "⭐ PROPRIO LUI: $m\n";
        } else {
            echo "- $m\n";
        }
    }
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
