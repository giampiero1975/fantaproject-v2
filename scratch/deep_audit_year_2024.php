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

echo "--- 🔍 DEEP AUDIT STAGIONE ANNO 2024 ---\n";

// Cerchiamo la stagione per ANNO invece che per ID
$yearRequested = 2024;
$s = Season::where('season_year', $yearRequested)->first();

if (!$s) {
    echo "⚠️  Analisi fallita: Nessuna stagione trovata per l'anno $yearRequested.\n";
    exit(1);
}

echo "Stagione Identificata: {$s->season_year} (ID a DB: {$s->id})\n";

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
    // Cerchiamo i log relativi alla stagione 2024 (ID 2)
    // Nota: il log segna "Processing Team [2024]"
    
    echo "Warnings per la stagione 2024:\n";
    $lines = explode("\n", $content);
    $warningCount = 0;
    $l4Count = 0;
    foreach ($lines as $line) {
        if (str_contains($line, "[2024]") || str_contains($line, "STAGIONE 2024")) {
            // Se troviamo un warning o un L4 nelle righe successive (andrebbe fatto un parsing serio)
            // Per ora prendiamo gli ultimi warning generici che prob. sono di quest'ultima run
        }
    }
    
    // Mostriamo gli ultimi 5 warning e L4 siccome abbiamo appena girato il sync 2024
    preg_match_all('/local.WARNING:.*|local.INFO:.*\[L4\].*/', $content, $matches);
    $warnings = array_reverse(array_filter($matches[0], fn($m) => str_contains($m, 'WARNING')));
    foreach (array_slice($warnings, 0, 5) as $w) {
        echo "  $w\n";
    }
    
    echo "\nUltime Creazioni L4:\n";
    $l4Entries = array_reverse(array_filter($matches[0], fn($m) => str_contains($m, '[L4]')));
    foreach (array_slice($l4Entries, 0, 10) as $l) {
        echo "  $l\n";
    }
}

// 4. Integrità ID
echo "\n--- [4] INTEGRITÀ ID (SOFT-DELETED IN ROSTER) ---\n";
$softDeletedInRoster = PlayerSeasonRoster::where('season_id', $s->id)
    ->whereHas('player', function($q) {
        $q->onlyTrashed();
    })->count();

echo "Record Roster 2024 che puntano a calciatori SOFT-DELETED: $softDeletedInRoster\n";

echo "\n--- 🏁 FINE AUDIT ---\n";
