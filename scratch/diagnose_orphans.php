<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlayerSeasonRoster;

$total = PlayerSeasonRoster::count();
$active = PlayerSeasonRoster::whereHas('player')->count();
$trashedOnly = PlayerSeasonRoster::whereHas('player', function($q) { $q->onlyTrashed(); })->count();
$totallyOrphan = PlayerSeasonRoster::whereDoesntHave('player', function($q) { $q->withTrashed(); })->count();

echo "--- 📊 DIAGNOSI ROSTER ---\n";
echo "Totale Roster: $total\n";
echo "Legati a Calciatori Attivi: $active\n";
echo "Legati a Calciatori CESSATI (Soft-deleted): $trashedOnly\n";
echo "Totalmente ORFANI (Player non esiste): $totallyOrphan\n";
echo "--- 🏁 DIAGNOSI COMPLETATA ---\n";
