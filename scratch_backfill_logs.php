<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ImportLog;
use App\Models\Season;

$logs = ImportLog::whereNull('season_id')->get();
echo "Found " . $logs->count() . " logs to process.\n";

foreach ($logs as $log) {
    if (preg_match('/stagione\s+(\d{4})\//', $log->details, $matches)) {
        $year = (int)$matches[1];
        $season = Season::where('season_year', $year)->first();
        if ($season) {
            $log->season_id = $season->id;
            
            // Extract Ceduti
            if (preg_match('/Ceduti:\s+(\d+)/', $log->details, $m2)) {
                $log->rows_ceduti = (int)$m2[1];
            }
            
            $log->save();
            echo "Updated Log {$log->id} for year {$year} (Ceduti: {$log->rows_ceduti})\n";
        }
    }
}
echo "Done.\n";
