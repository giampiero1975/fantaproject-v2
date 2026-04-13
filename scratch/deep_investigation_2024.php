<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "--- 🕵️ AUDIT CHIRURGICO DISCREPANZA ROSTER 2024 (ID 2) ---\n";

$sId = 2; // Stagione 2024

// 1. DUPLICATI INTERNI
$duplicates = DB::table('player_season_roster')
    ->select('player_id', DB::raw('COUNT(*) as count'))
    ->where('season_id', $sId)
    ->groupBy('player_id')
    ->having('count', '>', 1)
    ->get();

echo "Calciatori duplicati nello stesso roster: " . $duplicates->count() . "\n";
foreach ($duplicates->take(5) as $d) {
    $p = Player::withTrashed()->find($d->player_id);
    echo "  - {$p->name} (ID: {$d->player_id}): apparizioni {$d->count}\n";
}

// 2. CATEGORIZZAZIONE DEGLI 842 RECORD
echo "\nCategorizzazione dei 842 record:\n";

// A. Regolari (Listone + Mappati API)
$regularMapped = PlayerSeasonRoster::where('season_id', $sId)
    ->whereHas('player', function($q) {
        $q->whereNotNull('fanta_platform_id')->whereNotNull('api_football_data_id');
    })->count();

// B. Regolari non mappati (Listone senza API)
$regularUnmapped = PlayerSeasonRoster::where('season_id', $sId)
    ->whereHas('player', function($q) {
        $q->whereNotNull('fanta_platform_id')->whereNull('api_football_data_id');
    })->count();

// C. L4 (Creati da API, no ID Listone)
$l4Players = PlayerSeasonRoster::where('season_id', $sId)
    ->whereHas('player', function($q) {
        $q->whereNull('fanta_platform_id');
    })->count();

// D. Zombies (Soft deleted)
$zombies = PlayerSeasonRoster::where('season_id', $sId)
    ->whereHas('player', function($q) {
        $q->onlyTrashed();
    })->count();

echo "  - [A] Listone + API (Match OK): $regularMapped\n";
echo "  - [B] Listone senza API (Orfani): $regularUnmapped\n";
echo "  - [C] Nuovi L4 (Solo API): $l4Players\n";
echo "  - [D] Zombies (Soft-Deleted): $zombies\n";

$sum = $regularMapped + $regularUnmapped + $l4Players; // Zombies sono già inclusi in A o B se contiamo player (Lara filtered)
echo "Somma (A+B+C): $sum\n";

// 3. ESEMPIO DI 20record EXTRA (L4 o altro)
echo "\nEsempio 20 record 'Extra' (L4 o senza matching):\n";
$extras = PlayerSeasonRoster::where('season_id', $sId)
    ->whereHas('player', function($q) {
        $q->whereNull('fanta_platform_id');
    })
    ->with('player', 'team')
    ->limit(20)
    ->get();

foreach ($extras as $e) {
    echo "  - {$e->player->name} | Team: {$e->team->name} | API ID: {$e->player->api_football_data_id} | Origine: L4\n";
}

echo "--- 🏁 FINE AUDIT ---\n";
