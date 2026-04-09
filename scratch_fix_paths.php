<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ImportLog;

$logs = ImportLog::whereNull('file_path')->get();
echo "Found " . $logs->count() . " logs to fix paths.\n";

foreach ($logs as $log) {
    if ($log->original_file_name) {
        $path = 'imports/' . $log->original_file_name;
        $log->file_path = $path;
        $log->save();
        echo "Updated Log {$log->id} path to {$path}\n";
    }
}
echo "Done.\n";
