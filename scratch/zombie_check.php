<?php

use App\Models\Player;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$zombies = Player::withTrashed()
    ->with('parentTeam')
    ->whereNotNull('api_football_data_id')
    ->whereNull('fanta_platform_id')
    ->whereDoesntHave('historicalStats')
    ->whereDoesntHave('rosters')
    ->get();

echo "📊 DIAGNOSTICA ZOMBIE (con Squadra)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Totale Zombie trovati: " . $zombies->count() . "\n\n";

if ($zombies->count() > 0) {
    echo "Primi 20 esempi:\n";
    foreach ($zombies->take(20) as $z) {
        $team = $z->parentTeam ? $z->parentTeam->name : 'Nessuna';
        echo "- [ID: {$z->id}] {$z->name} | SQUADRA: {$team} | (API ID: {$z->api_football_data_id})\n";
    }
}
 else {
    echo "Nessun record corrisponde ai criteri. Il database sembra pulito rispetto a questa definizione.\n";
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
