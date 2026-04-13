<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "--- INIZIO RIPOPOLAMENTO RUOLI REGISTRY ---\n";

$players = Player::all();
$updated = 0;

foreach ($players as $p) {
    // Cerchiamo l'ultimo ruolo noto nei roster di questo giocatore
    $lastRole = DB::table('player_season_roster')
        ->where('player_id', $p->id)
        ->latest('id')
        ->value('role');
    
    if ($lastRole) {
        $p->update(['role' => $lastRole]);
        $updated++;
    }
}

echo "✅ Fine ripopolamento. Ruoli aggiornati: $updated su " . $players->count() . ".\n";
