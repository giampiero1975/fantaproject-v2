<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;

require __DIR__ . rtrim('/../vendor/autoload.php', '/');
$app = require_once __DIR__ . rtrim('/../bootstrap/app.php', '/');
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 TEST FUNZIONALE: ON DELETE CASCADE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    DB::beginTransaction();

    // 1. Creazione Player Temporaneo
    $player = Player::create([
        'name' => 'TEST CASCADE DELETE',
        'role' => 'C'
    ]);
    echo "1) Creato calciatore temporaneo: {$player->name} [ID: {$player->id}]\n";

    // 2. Creazione Roster (associamo a stagione 2025, team 1 per semplicità)
    DB::table('player_season_roster')->insert([
        'player_id' => $player->id,
        'season_id' => 1,
        'team_id' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "2) Creato record di roster associato.\n";

    // Verifica presenza
    $count = DB::table('player_season_roster')->where('player_id', $player->id)->count();
    echo "   Roster presenti prima della cancellazione: $count\n";

    // 3. HARD DELETE del Player
    echo "3) Esecuzione FORCE DELETE del calciatore...\n";
    $player->forceDelete();

    // 4. VERIFICA CASCADE
    $afterCount = DB::table('player_season_roster')->where('player_id', $player->id)->count();
    
    if ($afterCount === 0) {
        echo "✅ SUCCESS: Il record di roster è stato eliminato a cascata dal database!\n";
    } else {
        echo "❌ FAILURE: Il record di roster è ancora presente ($afterCount). Il cascade non ha funzionato.\n";
    }

    DB::commit();

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERRORE TEST: " . $e->getMessage() . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
