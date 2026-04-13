<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\Team;
use App\Models\PlayerSeasonRoster;

echo "--- 🔍 ANALISI PRE-SYNC 2023 ---\n";

$s = Season::where('season_year', 2023)->first();
if (!$s) {
    echo "❌ Errore: Stagione 2023 non trovata.\n";
    exit(1);
}

echo "Season 2023 ID: {$s->id}\n";

$rosters = PlayerSeasonRoster::where('season_id', $s->id)->count();
echo "Calciatori presenti in Roster (Listone): $rosters\n";

$teams = Team::whereHas('teamSeasons', function($q) use ($s) {
    $q->where('season_id', $s->id);
})->whereNotNull('api_id')->count();

echo "Squadre configurate con API_ID per il 2023: $teams\n";

if ($rosters === 0) {
    echo "⚠️ ATTENZIONE: Il roster per il 2023 è VUOTO. La sincronizzazione API creerà solo record L4 (Nuovi) invece di matchare.\n";
} else {
    echo "✅ Roster presente. Procediamo con il matching L1/L2/L3.\n";
}

echo "--- 🏁 ANALISI COMPLETATA ---\n";
