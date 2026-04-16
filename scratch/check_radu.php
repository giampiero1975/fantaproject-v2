<?php

use App\Models\Player;
use App\Models\Team;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 DIAGNOSTICA RADU\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$players = Player::withTrashed()->where('name', 'like', '%Radu%')->get();

echo "Trovati " . $players->count() . " calciatori con 'Radu':\n";
foreach ($players as $p) {
    echo "- [ID: {$p->id}] {$p->name} | FBref: " . ($p->fbref_id ?: 'NULL') . " | Stato: " . ($p->trashed() ? 'CEDUTO' : 'ATTIVO') . "\n";
    foreach ($p->rosters()->with('team', 'season')->get() as $r) {
        echo "  ↳ Roster: {$r->season->season_year} | {$r->team->name}\n";
    }
}

echo "\n🧪 TEST DI MATCHING (Simulato):\n";
$fbrefName = "Stefan Radu";
$trait = new class { use \App\Traits\FindsPlayerByName; };

foreach ($players as $p) {
    $isSimilar = $trait->namesAreSimilar($fbrefName, $p->name);
    echo "'$fbrefName' vs '{$p->name}' -> " . ($isSimilar ? '✅ MATCH!' : '❌ NO MATCH') . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
