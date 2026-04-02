<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Season;
use App\Models\TeamSeason;
use Illuminate\Support\Facades\DB;

echo "--- Seasons Table ---\n";
$seasons = Season::all();
foreach ($seasons as $s) {
    echo "ID: {$s->id} | Year: {$s->season_year} | Current: " . ($s->is_current ? 'YES' : 'NO') . "\n";
}

echo "\n--- TeamSeason (sample 5) ---\n";
$pivot = DB::table('team_season')->limit(5)->get();
foreach ($pivot as $row) {
    echo "ID: {$row->id} | Season ID: {$row->season_id} | Team ID: {$row->team_id}\n";
}

echo "\n--- Sync Logs (last 20 lines) ---\n";
$logFile = storage_path('logs/GestioneStagioni/stagioni.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    echo implode("", array_slice($lines, -20));
} else {
    echo "Log file not found.\n";
}
