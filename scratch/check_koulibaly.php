<?php

use App\Models\Player;
use App\Models\Team;
use App\Models\Season;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 DIAGNOSTICA KOULIBALY (ID 70)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$p = Player::withTrashed()->find(70);

if (!$p) {
    die("❌ Player ID 70 non trovato nel database.\n");
}

echo "Nome DB: " . $p->name . "\n";
echo "Stato: " . ($p->trashed() ? 'CEDUTO/ELIMINATO' : 'ATTIVO') . "\n";
echo "FBref ID attuale: " . ($p->fbref_id ?: 'NULL') . "\n";
echo "FantaID: " . ($p->fanta_platform_id ?: 'NULL') . "\n";

echo "\nPresenze in Roster:\n";
foreach ($p->rosters()->with('team', 'season')->get() as $r) {
    echo "- Stagione: {$r->season->season_year} | Squadra: {$r->team->name} [ID: {$r->team_id}]\n";
}

// Simuliamo il matching se avessimo "Kalidou Koulibaly" da FBref
$fbrefName = "Kalidou Koulibaly";
$trait = new class { use \App\Traits\FindsPlayerByName; };

echo "\n🧪 TEST DI MATCHING (Simulato):\n";
echo "FBref Name: '$fbrefName' vs DB Name: '{$p->name}'\n";

$isSimilar = $trait->namesAreSimilar($fbrefName, $p->name);
echo "Risultato Similarity: " . ($isSimilar ? '✅ MATCH!' : '❌ NO MATCH') . "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
