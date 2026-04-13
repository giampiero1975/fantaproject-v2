<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;
use App\Models\Player;

echo "--- 🔍 AUDIT ROSTER 2024 (ID 2) ---\n";

$total = PlayerSeasonRoster::where('season_id', 2)->count();
echo "📊 Totale record nel roster 2024: {$total}\n";

// Calciatori provenienti dal Listone (hanno fanta_platform_id)
$listoneCount = PlayerSeasonRoster::where('season_id', 2)
    ->whereHas('player', fn($q) => $q->whereNotNull('fanta_platform_id'))
    ->count();

// Calciatori L4 (NON hanno fanta_platform_id)
$l4Count = PlayerSeasonRoster::where('season_id', 2)
    ->whereHas('player', fn($q) => $q->whereNull('fanta_platform_id'))
    ->count();

echo "✅ Da Listone (Excel): {$listoneCount}\n";
echo "🆕 Creati da Sync (L4): {$l4Count}\n";

if ($l4Count > 0) {
    echo "\n📝 ESEMPIO GIOCATORI L4 (Primi 15):\n";
    $samples = PlayerSeasonRoster::where('season_id', 2)
        ->whereHas('player', fn($q) => $q->whereNull('fanta_platform_id'))
        ->with('player', 'team')
        ->limit(15)
        ->get();
    
    foreach ($samples as $s) {
        $age = $s->player->date_of_birth ? $s->player->date_of_birth->age : 'N/A';
        echo "   - {$s->player->name} ({$s->team->short_name}) | Età: {$age} | Ruolo: {$s->role}\n";
    }
}

// Controllo se ci sono duplicati "logici" (Stesso nome, stessa squadra, ma uno L4 e uno Listone)
echo "\n🕵️ Caccia ai duplicati (Match Falliti ma esistenti):\n";
$allPlayers = PlayerSeasonRoster::where('season_id', 2)->with('player')->get();
$names = [];
$dupes = 0;

foreach ($allPlayers as $p) {
    $key = strtolower($p->player->name) . '_' . $p->team_id;
    if (isset($names[$key])) {
        $dupes++;
        echo "   ⚠️ SOSPETTO DUPLICATO: '{$p->player->name}' in {$p->team->short_name}\n";
    }
    $names[$key] = true;
}

echo "🔎 Totale sospetti duplicati nome/squadra: {$dupes}\n";
echo "--- 🏁 FINE AUDIT ---";
