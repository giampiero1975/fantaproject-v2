<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "--- 🔍 ANALISI ECCEDENZE ROSTER 2024 ---\n";

$s2024 = Season::where('season_year', 2024)->first();
$totalRecords = PlayerSeasonRoster::where('season_id', $s2024->id)->count();
echo "Totale Record Roster 2024: $totalRecords\n";

// 1. Quanti sono associati a calciatori 'L4' (creati dall'API, senza ID Listone)
$l4Count = PlayerSeasonRoster::where('season_id', $s2024->id)
    ->whereHas('player', function($q) {
        $q->whereNull('fanta_platform_id');
    })->count();

// 2. Quanti sono associati a calciatori 'Regolari' (con ID Listone)
$regularCount = PlayerSeasonRoster::where('season_id', $s2024->id)
    ->whereHas('player', function($q) {
        $q->whereNotNull('fanta_platform_id');
    })->count();

// 3. Quanti sono orfani assoluti (senza player associato - non dovrebbe succedere con le FK)
$orphanRoster = PlayerSeasonRoster::where('season_id', $s2024->id)
    ->whereDoesntHave('player')->count();

echo "Calciatori L4 (Creati da API): $l4Count\n";
echo "Calciatori Regolari (Da Listone): $regularCount\n";
echo "Record Roster Orfani: $orphanRoster\n";

// Analisi distribuzione per squadra
echo "\nTop 5 Squadre per volume record:\n";
$teams = DB::table('player_season_roster')
    ->join('teams', 'player_season_roster.team_id', '=', 'teams.id')
    ->where('season_id', $s2024->id)
    ->select('teams.name', DB::raw('COUNT(*) as count'))
    ->groupBy('teams.name')
    ->orderByDesc('count')
    ->limit(5)
    ->get();

foreach ($teams as $t) {
    echo "  - {$t->name}: {$t->count} record\n";
}

echo "--- 🏁 FINE ANALISI ---\n";
