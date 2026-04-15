<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;

echo "--- DEEP ROSTER AUDIT (SEASON 2025) ---\n";

$roster = PlayerSeasonRoster::where('season_id', 1)->get();
$total = $roster->count();
$broken = 0;
$softDeleted = 0;
$withPlayer = 0;

foreach ($roster as $r) {
    if (!$r->player) {
        $broken++;
    } elseif ($r->player->trashed()) {
        $softDeleted++;
    } else {
        $withPlayer++;
    }
}

echo "Totale Roster 2025      : {$total}\n";
echo "Link Rotti (Null Player) : {$broken}\n";
echo "Soft Deleted Players     : {$softDeleted}\n";
echo "Giocatori Attivi         : {$withPlayer}\n";

if ($withPlayer > 0) {
    echo "\nCerco Theo nel roster attivo...\n";
    $theo = PlayerSeasonRoster::where('season_id', 1)
        ->whereHas('player', fn($q) => $q->where('name', 'like', '%theo%')->orWhere('name', 'like', '%hernandez%'))
        ->with('player')
        ->first();
    
    if ($theo) {
        echo "Trovato: " . $theo->player->name . " | FantaID: " . $theo->player->fanta_platform_id . "\n";
    } else {
        echo "Theo NON trovato nel roster attivo nemmeno con ricerca flessibile!\n";
    }
}
