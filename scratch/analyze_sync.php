<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use App\Models\ImportLog;

$log = ImportLog::where('import_type', 'fbref_surgical_sync')->latest()->first();
echo "Import Log ID: " . ($log->id ?? 'None') . "\n";
echo "Status: " . ($log->status ?? 'None') . "\n";
echo "Details: " . ($log->details ?? 'None') . "\n";
echo "Rows Processed: " . ($log->rows_processed ?? 0) . "\n";

$updatedPlayers = Player::whereNotNull('fbref_id')
    ->whereHas('rosters', fn($q) => $q->where('team_id', 22)->where('season_id', 5))
    ->get(['id', 'name', 'fbref_id']);

echo "Updated Players in Venezia 2024: " . $updatedPlayers->count() . "\n";
foreach ($updatedPlayers as $p) {
    echo "- {$p->name} (ID: {$p->fbref_id})\n";
}
