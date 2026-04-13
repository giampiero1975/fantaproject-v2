<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;

echo "--- 🩹 FIX SOFT-DELETE ORFANI 403 ---\n";

$teamIdsToExclude = [13, 15, 16, 20]; // Empoli, Salernitana, Frosinone, Monza

// Cerchiamo i calciatori che non hanno api_id
$players = Player::whereNull('api_football_data_id')
    ->where(function($q) use ($teamIdsToExclude) {
        // Hanno giocato in una di queste squadre (anche in passato, o avrebbero dovuto nel 2023)
        // Dato che abbiamo rimosso i roster nel rollback, cerchiamo di identificarli tramite i resti o se non hanno altre attività nel registro
        $q->whereDoesntHave('rosters'); // Non hanno più roster associati (perché li abbiamo cancellati)
    })
    ->get();

foreach ($players as $p) {
    if (!$p->trashed() && $p->fanta_platform_id) { // Solo quelli del listone che sono orfani totali ora
        $p->delete();
        echo "  - [SOFT-DELETE] {$p->name} (Orfano senza roster attivo)\n";
    }
}

echo "--- ✅ FINISH ---\n";
