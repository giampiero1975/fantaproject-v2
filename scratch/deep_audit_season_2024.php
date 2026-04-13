<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "--- 🔍 DEEP AUDIT STAGIONE 2024 ---\n";

// 0. Verifica ID
$s = Season::find(4);
if (!$s) {
    echo "⚠️  Attenzione: Nessuna stagione trovata con ID 4.\n";
    foreach(Season::all() as $season) {
        echo "  - ID {$season->id}: Year {$season->season_year}\n";
    }
    exit(1);
}
echo "Stagione Identificata: {$s->season_year} (ID: {$s->id})\n";

// 1. Analisi Struttura Roster
echo "\n--- [1] STRUTTURA ROSTER ---\n";
$total = PlayerSeasonRoster::where('season_id', $s->id)->count();
echo "Totale Record Roster: $total\n";

$teams = DB::table('player_season_roster')
    ->join('teams', 'player_season_roster.team_id', '=', 'teams.id')
    ->where('season_id', $s->id)
    ->select('teams.id', 'teams.name', DB::raw('COUNT(*) as count'))
    ->groupBy('teams.id', 'teams.name')
    ->orderBy('teams.name')
    ->get();

echo "Team presenti (" . $teams->count() . "):\n";
foreach ($teams as $t) {
    echo "  - [ID {$t->id}] {$t->name}: {$t->count} calciatori\n";
}

// 2. Verifica Campioni Casuali (Orfani)
echo "\n--- [2] CAMPIONI ORFANI (SENZA API ID) ---\n";
$orphans = Player::whereNull('api_football_data_id')
    ->whereHas('rosters', function($q) use ($s) {
        $q->where('season_id', $s->id);
    })
    ->inRandomOrder()
    ->limit(10)
    ->get();

foreach ($orphans as $o) {
    $currentTeam = PlayerSeasonRoster::where('player_id', $o->id)->where('season_id', $s->id)->first()?->team->name ?? 'N/A';
    echo "Player: {$o->name} (ID: {$o->id}) | Team 2024: $currentTeam\n";
    
    // Storia
    $history = PlayerSeasonRoster::where('player_id', $o->id)
        ->where('season_id', '!=', $s->id)
        ->with('team', 'season')
        ->get();
    if ($history->isNotEmpty()) {
        foreach ($history as $h) {
            echo "  └─ [Anno {$h->season->season_year}] Team: {$h->team->name}\n";
        }
    } else {
        echo "  └─ Nessuna storia precedente/successiva.\n";
    }
}

// 3. Ispezione Log
echo "\n--- [3] ISPEZIONE LOG (Sincronizzazione 2024) ---\n";
$logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
if (File::exists($logPath)) {
    $content = File::get($logPath);
    // Filtriamo per log della stagione 2024
    // Cerchiamo i primi 5 warning
    preg_match_all('/local.WARNING:.*|local.INFO:.*\[L4\].*/', $content, $matches);
    
    echo "Primi 5 Warning rilevati:\n";
    $warnings = array_filter($matches[0], fn($m) => str_contains($m, 'WARNING'));
    foreach (array_slice($warnings, 0, 5) as $w) {
        echo "  $w\n";
    }
    
    echo "\nCreazioni Nuovi Record (L4):\n";
    $l4s = array_filter($matches[0], fn($m) => str_contains($m, '[L4]'));
    foreach (array_slice($l4s, 0, 10) as $l) {
        echo "  $l\n";
    }
} else {
    echo "File di log non trovato.\n";
}

// 4. Integrità ID (Soft-Deleted)
echo "\n--- [4] INTEGRITÀ ID (SOFT-DELETED IN ROSTER) ---\n";
$softDeletedInRoster = PlayerSeasonRoster::where('season_id', $s->id)
    ->whereHas('player', function($q) {
        $q->onlyTrashed();
    })->count();

echo "Record Roster 2024 che puntano a calciatori SOFT-DELETED: $softDeletedInRoster\n";

echo "\n--- 🏁 FINE AUDIT ---\n";
