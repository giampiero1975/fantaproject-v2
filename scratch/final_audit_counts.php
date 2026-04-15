<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;

$count2024 = PlayerSeasonRoster::where('season_id', 2)
    ->whereHas('player', fn($q) => $q->whereNull('fanta_platform_id'))
    ->count();

$total2024 = PlayerSeasonRoster::where('season_id', 2)->count();

echo "--- AUDIT FINALE 2024 ---\n";
echo "Record Totali: {$total2024}\n";
echo "Record L4 (Creati API): {$count2024}\n";
echo "--------------------------\n";
