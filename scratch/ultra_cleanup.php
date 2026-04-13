<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Player;
use Illuminate\Support\Facades\File;

echo "--- INIZIO CLEANUP ULTRA-CHIRURGICO ---\n";

// 1. Rimozione Cloni
echo "1. Rimozione cloni (ID > 2000)... ";
$deleted = Player::where('id', '>', 2000)->delete();
echo "Fatto ($deleted record rimossi).\n";

// 2. Azzeramento Log
echo "2. Azzeramento RosterHistoricalSync.log... ";
$logPath = storage_path('logs/Roster/RosterHistoricalSync.log');
if (File::exists($logPath)) {
    File::put($logPath, '');
    echo "Fatto.\n";
} else {
    echo "File non trovato (saltato).\n";
}

echo "--- CLEANUP COMPLETATO ---\n";
