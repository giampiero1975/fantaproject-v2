<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\PlayerSeasonRoster;

echo "--- ROSTER ID AUDIT (SEASON 2025) ---\n";

$rosterIds = PlayerSeasonRoster::where('season_id', 1)->pluck('player_id')->toArray();
$players = Player::whereIn('id', $rosterIds)->get();

$total = $players->count();
$withId = $players->whereNotNull('fanta_platform_id')->count();
$withoutId = $total - $withId;

echo "Totale Roster 2025     : {$total}\n";
echo "Con Fanta ID Popolato : {$withId}\n";
echo "Senza Fanta ID        : {$withoutId}\n";

if ($withoutId > 0) {
    echo "\nPrimi 20 giocatori senza ID in Roster:\n";
    foreach ($players->whereNull('fanta_platform_id')->take(20) as $p) {
        echo " - ID: {$p->id} | Nome: {$p->name}\n";
    }
}

echo "\n--- SAMPLE ID CHECK (2021 FILE VS DB) ---\n";
// Theo Hernandez (ID Excel 2021: 4292)
$theo = Player::where('name', 'like', '%Hernandez%')->first();
echo "Theo Hernandez in DB: " . ($theo ? "SI (ID DB: {$theo->fanta_platform_id})" : "NO") . "\n";

// Vlahovic (ID Excel 2021: 2841)
$vlaho = Player::where('name', 'like', '%Vlahovic%')->first();
echo "Vlahovic in DB       : " . ($vlaho ? "SI (ID DB: {$vlaho->fanta_platform_id})" : "NO") . "\n";
