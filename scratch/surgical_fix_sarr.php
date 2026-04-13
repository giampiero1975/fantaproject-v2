<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;

echo "--- 🏥 SURGICAL FIX: AMIN SARR ---\n";

$realSarr = Player::where('name', 'Sarr A.')->first();
$duplicateSarr = Player::where('name', 'Amin Sarr')->first();

if ($realSarr && $duplicateSarr) {
    echo "Trovati: Real (ID: {$realSarr->id}), Duplicate (ID: {$duplicateSarr->id})\n";
    
    // 1. Rimuovi orfani creati dal duplicato nel roster (se presenti)
    $rosterCount = PlayerSeasonRoster::where('player_id', $duplicateSarr->id)->delete();
    echo "🗑️ Rimosso $rosterCount record dal roster per il duplicato.\n";
    
    // 2. Elimina il duplicato (per liberare l'ID API unico)
    $duplicateSarr->forceDelete();
    echo "🗑️ Calciatore duplicato 'Amin Sarr' eliminato.\n";

    // 3. Aggiorna il record reale
    $realSarr->api_football_data_id = 124473;
    $realSarr->save();
    echo "✅ Sarr A. aggiornato con API ID 124473.\n";
    
} else {
    echo "⚠️ Errore: Uno dei record non è stato trovato.\n";
    if ($realSarr) echo "- Sarr A. trovato.\n";
    if ($duplicateSarr) echo "- Amin Sarr trovato.\n";
}

echo "--- 🏁 FINE FIX ---\n";
