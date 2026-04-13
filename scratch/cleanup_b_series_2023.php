<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;
use App\Models\TeamSeason;

echo "--- AVVIO BONIFICA CHIRURGICA SERIE B (Season 2023) ---\n";

// 1. Identifichiamo i Team di Serie B nella stagione 2023 (ID 3)
$bTeamIds = TeamSeason::where('season_id', 3)
    ->where('league_id', '!=', 1)
    ->pluck('team_id')
    ->toArray();

echo "Team di Serie B rilevati nel 2023: " . count($bTeamIds) . "\n";

// 2. Troviamo i calciatori creati oggi (L4) associati a questi team
$playersToDelete = Player::where('created_at', '>', '2026-04-12 08:30:00')
    ->whereHas('rosters', function($q) use ($bTeamIds) {
        $q->whereIn('team_id', $bTeamIds)->where('season_id', 3);
    })->get();

$count = $playersToDelete->count();
echo "Calciatori L4 (Serie B) pronti per l'eliminazione: " . $count . "\n";

foreach ($playersToDelete as $p) {
    // Rimuoviamo il record del roster (soft delete non applicato qui di solito, ma per sicurezza)
    PlayerSeasonRoster::where('player_id', $p->id)->where('season_id', 3)->delete();
    // Eliminiamo il calciatore
    $p->forceDelete();
}

// 3. Resettiamo gli ID API per le anagrafiche della Serie A per sicurezza (se vogliamo un run pulito)
// Ma l'utente ha chiesto solo di fermarsi al fix.

echo "--- BONIFICA COMPLETATA: $count calciatori rimossi. ---\n";
