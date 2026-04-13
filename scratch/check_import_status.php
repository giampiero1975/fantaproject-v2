<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ImportLog;
use App\Models\Season;
use App\Models\PlayerSeasonRoster;

echo "--- 🔍 ANALISI STATO IMPORTAZIONE ---\n";

foreach (Season::all() as $s) {
    echo "Season: {$s->season_year} (ID: {$s->id})\n";
    $rosters = PlayerSeasonRoster::where('season_id', $s->id)->count();
    echo " - Record a DB: $rosters\n";
    
    $lastLog = ImportLog::where('season_id', $s->id)
        ->where('import_type', 'roster_quotazioni')
        ->latest()
        ->first();
        
    if ($lastLog) {
        echo " - Ultimo Log ID: {$lastLog->id} ({$lastLog->status})\n";
        echo " - File Originale: {$lastLog->original_file_name}\n";
        echo " - Percorso: {$lastLog->file_path}\n";
    } else {
        echo " - Nessun log di importazione per questa stagione.\n";
    }
}

echo "--- 🏁 ANALISI COMPLETATA ---\n";
