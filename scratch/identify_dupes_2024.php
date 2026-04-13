<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;
use Illuminate\Support\Str;

echo "--- 🕵️ INVESTIGAZIONE DUPLICATI ROSTER 2024 (ID 2) ---\n";

// 1. Recuperiamo tutto il roster 2024 con i dati anagrafici
$roster = PlayerSeasonRoster::where('season_id', 2)
    ->with(['player', 'team'])
    ->get();

$registry = [];
$potentialDupes = [];

foreach ($roster as $r) {
    if (!$r->player) continue;

    // Normalizziamo il nome per il confronto (es: "Samuel Chukwueze" -> "chukwueze")
    // Usiamo l'ultimo token del nome come chiave primaria di sospetto per la squadra
    $parts = explode(' ', strtolower(Str::ascii($r->player->name)));
    $lastName = end($parts);
    
    $key = $r->team_id . '_' . $lastName;
    
    if (!isset($registry[$key])) {
        $registry[$key] = [];
    }
    
    $registry[$key][] = $r;
}

echo "🔍 Analisi per Nome-Squadra...\n\n";

$count = 0;
foreach ($registry as $key => $matches) {
    if (count($matches) > 1) {
        // Abbiamo più di un giocatore con lo stesso 'cognome' nella stessa squadra.
        // Verifichiamo se uno è L4 (No FantaID) e uno è Listone (Has FantaID)
        $hasListone = false;
        $hasL4 = false;
        
        foreach ($matches as $m) {
            if ($m->player->fanta_platform_id) $hasListone = true;
            else $hasL4 = true;
        }
        
        if ($hasListone && $hasL4) {
            $count++;
            echo "⚠️ [DUPE #{$count}] Sospetto sdoppiamento:\n";
            foreach ($matches as $m) {
                $type = $m->player->fanta_platform_id ? "LISTONE (ID: {$m->player->id}, FantaID: {$m->player->fanta_platform_id})" : "L4-SYNC (ID: {$m->player->id}, API: {$m->player->api_football_data_id})";
                echo "   - {$m->player->name} [{$m->team->short_name}] -> {$type}\n";
            }
            echo "\n";
        }
    }
}

echo "--- 🏁 FINE INVESTIGAZIONE (Trovati {$count} gruppi sospetti) ---";
