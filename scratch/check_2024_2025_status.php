<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\PlayerSeasonRoster;

echo "--- 🔍 ANALISI PRE-SYNC 2024/2025 ---\n";

foreach ([2024, 2025] as $year) {
    $s = Season::where('season_year', $year)->first();
    if (!$s) {
        echo "Season $year: NON TROVATA\n";
        continue;
    }
    $rosters = PlayerSeasonRoster::where('season_id', $s->id)->count();
    echo "Season $year (ID: {$s->id}): $rosters record in roster.\n";
}

echo "--- 🏁 ANALISI COMPLETATA ---\n";
